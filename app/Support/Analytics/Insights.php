<?php

namespace App\Support\Analytics;

use App\Models\Company;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Le statistiche piu' a fondo: obiettivi, nuovi e di ritorno, dettaglio per
 * azienda e per pagina, regioni e citta'. Stessi filtri di Report (periodo e
 * sito scelto), numeri calcolati sulle stesse tabelle.
 */
final class Insights
{
    public function __construct(
        public readonly Period $period,
        public readonly ?string $host = null,
    ) {}

    private function views(?Period $period = null): Builder
    {
        $period ??= $this->period;

        return DB::table('page_views')
            ->whereBetween('day', [$period->from->toDateString(), $period->to->toDateString()])
            ->when($this->host, fn (Builder $query) => $query->where('host', $this->host));
    }

    private function entries(?Period $period = null): Builder
    {
        return $this->views($period)->where('is_entry', true);
    }

    private function conversions(?Period $period = null): Builder
    {
        $period ??= $this->period;

        return DB::table('conversions')
            ->whereBetween('day', [$period->from->toDateString(), $period->to->toDateString()])
            ->when($this->host, fn (Builder $query) => $query->where('host', $this->host));
    }

    /* Obiettivi ---------------------------------------------------------- */

    /**
     * Gli obiettivi raggiunti, con il confronto col periodo precedente e la
     * quota sulle visite. Per gli ordini anche l'incasso.
     *
     * @return list<array{goal: string, label: string, count: int, previous: int, change: ?float, rate: float, value: float}>
     */
    public function goals(): array
    {
        $now = $this->goalTotals($this->period);
        $before = $this->goalTotals($this->period->previous());
        $sessions = (int) $this->entries()->count();

        return collect(Conversions::LABELS)->map(fn ($label, $goal) => [
            'goal' => $goal,
            'label' => $label,
            'count' => $now[$goal]['count'],
            'previous' => $before[$goal]['count'],
            'change' => $before[$goal]['count'] > 0 ? ($now[$goal]['count'] - $before[$goal]['count']) / $before[$goal]['count'] : null,
            'rate' => $sessions > 0 ? $now[$goal]['sessions'] / $sessions : 0.0,
            'value' => $now[$goal]['value'],
        ])->values()->all();
    }

    /** @return array<string, array{count: int, sessions: int, value: float}> */
    private function goalTotals(Period $period): array
    {
        $rows = $this->conversions($period)
            ->selectRaw('goal, count(*) as total, count(distinct session_id) as sessions, coalesce(sum(value), 0) as value')
            ->groupBy('goal')->get()->keyBy('goal');

        return collect(array_keys(Conversions::LABELS))->mapWithKeys(fn ($goal) => [$goal => [
            'count' => (int) ($rows[$goal]->total ?? 0),
            'sessions' => (int) ($rows[$goal]->sessions ?? 0),
            'value' => (float) ($rows[$goal]->value ?? 0),
        ]])->all();
    }

    /**
     * Il percorso che porta all'ordine, contato in visite: quante sono
     * arrivate a ogni gradino. Ogni gradino e' un sottoinsieme del precedente
     * solo in teoria (chi arriva diretto alla cassa salta le schede), per
     * questo la quota e' sempre sul totale delle visite.
     *
     * @return list<array{label: string, count: int, share: float, step: float}>
     */
    public function funnel(): array
    {
        $sessions = (int) $this->entries()->count();

        $pagesWith = fn (callable $where) => (int) $this->views()->where($where)->distinct()->count('session_id');
        $goal = fn (string $name) => (int) $this->conversions()->where('goal', $name)->whereNotNull('session_id')->distinct()->count('session_id');

        $steps = [
            ['Visite', $sessions],
            ['Hanno guardato un\'azienda o un prodotto', $pagesWith(fn ($q) => $q->where(fn ($w) => $w->where('path', 'like', '/aziende/%')->orWhere('path', 'like', '/prodotti/%')))],
            ['Hanno messo un prodotto nel carrello', $goal(Conversions::CART)],
            ['Hanno aperto la cassa', $pagesWith(fn ($q) => $q->where('path', '/pagamento'))],
            ['Hanno fatto un ordine', $goal(Conversions::ORDER)],
        ];

        $previous = null;

        return array_map(function ($step) use ($sessions, &$previous) {
            $row = [
                'label' => $step[0],
                'count' => $step[1],
                'share' => $sessions > 0 ? $step[1] / $sessions : 0.0,
                // Quanti del gradino prima arrivano a questo.
                'step' => $previous ? ($previous > 0 ? min(1, $step[1] / $previous) : 0.0) : 1.0,
            ];
            $previous = $step[1];

            return $row;
        }, $steps);
    }

    /**
     * Da dove arrivano gli ordini e i contatti: per ogni fonte le visite,
     * gli obiettivi e la quota di visite che li hanno raggiunti.
     *
     * @return Collection<int, object{source: string, channel_label: string, sessions: int, orders: int, signups: int, contacts: int, revenue: float, rate: float}>
     */
    public function conversionSources(int $limit = 10): Collection
    {
        $sessions = $this->entries()->selectRaw('channel, source, count(*) as total')->groupBy('channel', 'source')->get();
        $done = $this->conversions()->whereNotNull('channel')
            ->selectRaw('channel, source, goal, count(*) as total, coalesce(sum(value), 0) as value')
            ->groupBy('channel', 'source', 'goal')->get();

        $rows = [];
        $key = fn ($row) => ($row->channel ?? 'direct').'|'.($row->source ?? '');

        foreach ($sessions as $row) {
            $rows[$key($row)] = ['channel' => $row->channel ?? 'direct', 'source' => $row->source, 'sessions' => (int) $row->total,
                'orders' => 0, 'signups' => 0, 'contacts' => 0, 'revenue' => 0.0];
        }

        foreach ($done as $row) {
            $k = $key($row);
            $rows[$k] ??= ['channel' => $row->channel ?? 'direct', 'source' => $row->source, 'sessions' => 0,
                'orders' => 0, 'signups' => 0, 'contacts' => 0, 'revenue' => 0.0];

            match ($row->goal) {
                Conversions::ORDER => [$rows[$k]['orders'] += (int) $row->total, $rows[$k]['revenue'] += (float) $row->value],
                Conversions::SIGNUP => $rows[$k]['signups'] += (int) $row->total,
                Conversions::CONTACT => $rows[$k]['contacts'] += (int) $row->total,
                default => null,
            };
        }

        return collect($rows)
            ->filter(fn ($r) => $r['orders'] + $r['signups'] + $r['contacts'] > 0)
            ->map(fn ($r) => (object) [
                'source' => $r['source'] ?? 'Accesso diretto',
                'channel_label' => TrafficSource::LABELS[$r['channel']] ?? 'Altro',
                'sessions' => $r['sessions'],
                'orders' => $r['orders'],
                'signups' => $r['signups'],
                'contacts' => $r['contacts'],
                'revenue' => $r['revenue'],
                'rate' => $r['sessions'] > 0 ? min(1, ($r['orders'] + $r['signups'] + $r['contacts']) / $r['sessions']) : 0.0,
            ])
            ->sortByDesc(fn ($r) => [$r->orders, $r->signups + $r->contacts, $r->sessions])
            ->take($limit)->values();
    }

    /* Nuovi e di ritorno ------------------------------------------------- */

    public function returningEnabled(): bool
    {
        return (bool) config('ksm.analytics.returning_visitors');
    }

    /**
     * Visite di chi e' alla prima visita e di chi torna. "Non noto" sono le
     * visite senza cookie (prima di accenderlo, o per chi li rifiuta).
     *
     * @return array{new: int, returning: int, unknown: int, total: int}
     */
    public function audience(): array
    {
        $row = $this->entries()->selectRaw(
            'sum(case when is_returning = 1 then 1 else 0 end) as back, '
            .'sum(case when is_returning = 0 then 1 else 0 end) as fresh, '
            .'sum(case when is_returning is null then 1 else 0 end) as unknown'
        )->first();

        $new = (int) ($row->fresh ?? 0);
        $back = (int) ($row->back ?? 0);
        $unknown = (int) ($row->unknown ?? 0);

        return ['new' => $new, 'returning' => $back, 'unknown' => $unknown, 'total' => $new + $back + $unknown];
    }

    /**
     * Chi torna fa piu' pagine e resta di piu'? Il confronto fra le due
     * categorie, per visita.
     *
     * @return list<array{key: string, label: string, sessions: int, pages: float, duration: float}>
     */
    public function audienceBehaviour(): array
    {
        $out = [];

        foreach ([['new', 'Nuovi', 0], ['returning', 'Di ritorno', 1]] as [$key, $label, $flag]) {
            $ids = $this->entries()->where('is_returning', $flag);
            $sessions = (int) (clone $ids)->count();

            $row = $sessions > 0
                ? $this->views()->whereIn('session_id', (clone $ids)->select('session_id'))
                    ->selectRaw('count(*) as pages, coalesce(sum(duration), 0) as seconds')->first()
                : null;

            $out[] = [
                'key' => $key,
                'label' => $label,
                'sessions' => $sessions,
                'pages' => $sessions > 0 ? (int) $row->pages / $sessions : 0.0,
                'duration' => $sessions > 0 ? (int) $row->seconds / $sessions : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Quante persone tornano nelle settimane dopo la prima visita: le ultime
     * otto settimane, una riga per settimana di prima visita.
     *
     * @return list<array{label: string, size: int, weeks: list<?float>}>
     */
    public function cohorts(int $weeks = 8): array
    {
        $thisWeek = CarbonImmutable::now(config('ksm.analytics.timezone'))->startOfWeek();
        $start = $thisWeek->subWeeks($weeks - 1);

        $first = DB::table('page_views')->whereNotNull('vid')
            ->when($this->host, fn (Builder $query) => $query->where('host', $this->host))
            ->selectRaw('vid, min(day) as first_day')->groupBy('vid')
            ->havingRaw('min(day) >= ?', [$start->toDateString()])
            ->pluck('first_day', 'vid');

        if ($first->isEmpty()) {
            return [];
        }

        $active = [];

        foreach ($first->keys()->chunk(500) as $chunk) {
            DB::table('page_views')->whereIn('vid', $chunk->all())->where('day', '>=', $start->toDateString())
                ->when($this->host, fn (Builder $query) => $query->where('host', $this->host))
                ->select('vid', 'day')->distinct()->get()
                ->each(function ($row) use (&$active) {
                    $active[$row->vid][] = $row->day;
                });
        }

        $cohorts = [];

        foreach ($first as $vid => $day) {
            $born = CarbonImmutable::parse($day)->startOfWeek();
            $index = (int) $start->diffInWeeks($born);
            $cohorts[$index]['size'] = ($cohorts[$index]['size'] ?? 0) + 1;

            foreach (array_unique(array_map(fn ($d) => (int) $born->diffInWeeks(CarbonImmutable::parse($d)->startOfWeek()), $active[$vid] ?? [])) as $offset) {
                $cohorts[$index]['back'][$offset] = ($cohorts[$index]['back'][$offset] ?? 0) + 1;
            }
        }

        ksort($cohorts);
        $rows = [];

        foreach ($cohorts as $index => $cohort) {
            $born = $start->addWeeks($index);
            $reach = (int) $born->diffInWeeks($thisWeek);
            $line = [];

            for ($w = 0; $w <= $reach; $w++) {
                $line[] = ($cohort['back'][$w] ?? 0) / $cohort['size'];
            }

            $rows[] = ['label' => 'Dal '.$born->format('d/m'), 'size' => $cohort['size'], 'weeks' => $line];
        }

        return $rows;
    }

    /* Aziende e prodotti ------------------------------------------------- */

    /**
     * Le schede azienda piu' viste, con il nome al posto dell'indirizzo.
     *
     * @return Collection<int, object{path: string, name: string, views: int, visitors: int, entries: int}>
     */
    public function companies(int $limit = 10): Collection
    {
        $rows = $this->catalogue('/aziende/');
        $names = Company::query()->whereIn('slug', $rows->pluck('slug'))->pluck('name', 'slug');

        return $rows->map(fn ($row) => $row + ['name' => $names[$row['slug']] ?? $row['slug']])
            ->take($limit)->map(fn ($row) => (object) $row)->values();
    }

    /** @return Collection<int, object{path: string, name: string, company: ?string, views: int, visitors: int, entries: int}> */
    public function products(int $limit = 10): Collection
    {
        $rows = $this->catalogue('/prodotti/');
        $products = Product::query()->with('company:id,name')->whereIn('slug', $rows->pluck('slug'))->get()->keyBy('slug');

        return $rows->map(fn ($row) => $row + [
            'name' => $products[$row['slug']]->name ?? $row['slug'],
            'company' => $products[$row['slug']]->company->name ?? null,
        ])->take($limit)->map(fn ($row) => (object) $row)->values();
    }

    /** Pagine `/prefisso/{slug}` con le viste: solo la scheda, non le sottopagine. */
    private function catalogue(string $prefix): Collection
    {
        return $this->views()->where('path', 'like', $prefix.'%')
            ->selectRaw('path, count(*) as views, count(distinct visitor) as visitors, sum(case when is_entry = 1 then 1 else 0 end) as entries')
            ->groupBy('path')->orderByDesc('views')->limit(60)->get()
            ->filter(fn ($row) => preg_match('#^'.preg_quote($prefix, '#').'[^/]+$#', $row->path) === 1)
            ->map(fn ($row) => [
                'path' => $row->path,
                'slug' => substr($row->path, strlen($prefix)),
                'views' => (int) $row->views,
                'visitors' => (int) $row->visitors,
                'entries' => (int) $row->entries,
            ])->values();
    }

    /**
     * I siti delle singole aziende (sul loro dominio): visite e obiettivi.
     *
     * @return Collection<int, object{name: string, host: string, views: int, visitors: int, sessions: int, goals: int}>
     */
    public function storefronts(int $limit = 10): Collection
    {
        $rows = $this->views()->where('site_type', 'company')
            ->selectRaw('site_id, host, count(*) as views, count(distinct visitor) as visitors, sum(case when is_entry = 1 then 1 else 0 end) as sessions')
            ->groupBy('site_id', 'host')->orderByDesc('views')->limit($limit)->get();

        $names = Company::query()->whereIn('id', $rows->pluck('site_id'))->pluck('name', 'id');
        $goals = $this->conversions()->selectRaw('host, count(*) as total')->groupBy('host')->pluck('total', 'host');

        return $rows->map(fn ($row) => (object) [
            'name' => $names[$row->site_id] ?? $row->host,
            'host' => $row->host,
            'views' => (int) $row->views,
            'visitors' => (int) $row->visitors,
            'sessions' => (int) $row->sessions,
            'goals' => (int) ($goals[$row->host] ?? 0),
        ]);
    }

    /* Ricerche ----------------------------------------------------------- */

    /**
     * Cosa si cerca nel sito (parametro `cerca`), le parole piu' frequenti.
     *
     * @return Collection<int, object{term: string, count: int}>
     */
    public function searches(int $limit = 10): Collection
    {
        return $this->views()->whereNotNull('search')
            ->selectRaw('search, count(*) as total')->groupBy('search')
            ->orderByDesc('total')->orderBy('search')->limit($limit)->get()
            ->map(fn ($row) => (object) ['term' => $row->search, 'count' => (int) $row->total]);
    }

    /**
     * Le parole con cui si arriva dai motori di ricerca, quando il motore le
     * comunica (Google no: vedi TrafficSource) o la campagna le indica (utm_term).
     *
     * @return Collection<int, object{term: string, source: ?string, count: int}>
     */
    public function keywords(int $limit = 10): Collection
    {
        return $this->entries()->whereNotNull('keyword')
            ->selectRaw('keyword, source, count(*) as total')->groupBy('keyword', 'source')
            ->orderByDesc('total')->orderBy('keyword')->limit($limit)->get()
            ->map(fn ($row) => (object) ['term' => $row->keyword, 'source' => $row->source, 'count' => (int) $row->total]);
    }

    /* Regioni e citta' --------------------------------------------------- */

    /** Le visite dall'Italia che hanno una regione: serve a sapere se l'archivio geografico lavora. */
    public function hasPlaces(): bool
    {
        return $this->entries()->where('country', 'IT')->whereNotNull('region')->exists();
    }

    /** @return Collection<int, object{label: string, count: int, share: float}> */
    public function regions(int $limit = 20): Collection
    {
        return $this->places('region', $limit);
    }

    /** @return Collection<int, object{label: string, count: int, share: float}> */
    public function cities(int $limit = 15): Collection
    {
        return $this->places('city', $limit);
    }

    private function places(string $column, int $limit): Collection
    {
        $rows = $this->entries()->where('country', 'IT')->whereNotNull($column)
            ->selectRaw("$column as name, count(*) as total")->groupBy($column)
            ->orderByDesc('total')->orderBy($column)->get();

        $all = max(1, (int) $rows->sum('total'));

        return $rows->take($limit)->map(fn ($row) => (object) [
            'label' => $row->name,
            'count' => (int) $row->total,
            'share' => $row->total / $all,
        ])->values();
    }

    /* Uscite ------------------------------------------------------------- */

    /**
     * Le pagine da cui si esce dal sito: l'ultima di ogni visita. La quota e'
     * sulle volte che la pagina e' stata vista.
     *
     * @return Collection<int, object{path: string, exits: int, views: int, rate: float}>
     */
    public function exitPages(int $limit = 10): Collection
    {
        $last = $this->views()->selectRaw('max(id) as id')->groupBy('session_id');
        $views = $this->views()->selectRaw('path, count(*) as total')->groupBy('path')->pluck('total', 'path');

        return $this->views()->whereIn('id', $last)
            ->selectRaw('path, count(*) as exits')->groupBy('path')
            ->orderByDesc('exits')->orderBy('path')->limit($limit)->get()
            ->map(fn ($row) => (object) [
                'path' => $row->path,
                'exits' => (int) $row->exits,
                'views' => (int) ($views[$row->path] ?? $row->exits),
                'rate' => min(1, $row->exits / max(1, (int) ($views[$row->path] ?? $row->exits))),
            ]);
    }

    /* Dettaglio di una pagina -------------------------------------------- */

    /**
     * Tutto su una pagina sola: viste, ingressi, uscite, rimbalzi, tempo, da
     * dove si arriva, dove si va dopo.
     *
     * @return array{views: int, visitors: int, entries: int, exits: int, bounces: int, avg_time: float, previous: Collection, next: Collection, sources: Collection, series: list<array{label: string, views: int}>}
     */
    public function page(string $path): array
    {
        $own = fn () => $this->views()->where('path', $path);

        $row = $own()->selectRaw('count(*) as views, count(distinct visitor) as visitors, '
            .'sum(case when is_entry = 1 then 1 else 0 end) as entries, avg(nullif(duration, 0)) as avg_time')->first();

        $last = $this->views()->selectRaw('max(id) as id')->groupBy('session_id');
        $exits = (int) $own()->whereIn('id', $last)->count();

        $pagesPerSession = $this->views()->selectRaw('session_id, count(*) as pages')->groupBy('session_id');
        $bounces = (int) $own()->where('is_entry', true)
            ->joinSub($pagesPerSession, 's', 's.session_id', '=', 'page_views.session_id')
            ->where('s.pages', 1)->count();

        $sources = $own()->where('is_entry', true)
            ->selectRaw('channel, source, count(*) as total')->groupBy('channel', 'source')
            ->orderByDesc('total')->limit(8)->get()
            ->map(fn ($r) => (object) [
                'label' => $r->source ?? 'Accesso diretto',
                'channel_label' => TrafficSource::LABELS[$r->channel ?? 'direct'] ?? 'Altro',
                'count' => (int) $r->total,
            ]);

        return [
            'views' => (int) ($row->views ?? 0),
            'visitors' => (int) ($row->visitors ?? 0),
            'entries' => (int) ($row->entries ?? 0),
            'exits' => $exits,
            'bounces' => $bounces,
            'avg_time' => (float) ($row->avg_time ?? 0),
            'previous' => $this->neighbours($path, 'previous'),
            'next' => $this->neighbours($path, 'next'),
            'sources' => $sources,
            'series' => $this->pageSeries($path),
        ];
    }

    /** Le pagine viste subito prima o subito dopo, nella stessa visita. */
    private function neighbours(string $path, string $direction, int $limit = 8): Collection
    {
        [$agg, $cmp] = $direction === 'next' ? ['min', '>'] : ['max', '<'];

        $query = DB::table('page_views as p')
            ->join('page_views as n', 'n.id', '=', DB::raw("(select $agg(x.id) from page_views x where x.session_id = p.session_id and x.id $cmp p.id)"))
            ->where('p.path', $path)
            ->whereBetween('p.day', [$this->period->from->toDateString(), $this->period->to->toDateString()])
            ->when($this->host, fn (Builder $q) => $q->where('p.host', $this->host))
            ->selectRaw('n.path as path, count(*) as total')
            ->groupBy('n.path')->orderByDesc('total')->orderBy('n.path')->limit($limit);

        return $query->get()->map(fn ($row) => (object) ['path' => $row->path, 'count' => (int) $row->total]);
    }

    /** @return list<array{label: string, views: int}> */
    private function pageSeries(string $path): array
    {
        $rows = $this->views()->where('path', $path)->selectRaw('day, count(*) as total')->groupBy('day')->pluck('total', 'day');
        $out = [];

        for ($day = $this->period->from; $day <= $this->period->to; $day = $day->addDay()) {
            $out[] = ['label' => $day->format('d/m'), 'views' => (int) ($rows[$day->toDateString()] ?? 0)];
        }

        return $out;
    }
}
