<?php

namespace App\Support\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tutti i numeri della pagina Statistiche visite.
 *
 * Definizioni, perche' i numeri si leggano bene:
 *  - pagine viste: ogni pagina servita;
 *  - visite: gruppi di pagine della stessa persona senza pause oltre i 30
 *    minuti, contate sulla prima pagina (is_entry);
 *  - visitatori: persone distinte per giorno. L'impronta cambia ogni notte,
 *    quindi chi torna dopo giorni conta di nuovo: e' il prezzo di non usare cookie;
 *  - rimbalzo: visita che si e' fermata alla prima pagina;
 *  - durata: secondi di attenzione davvero passati sulla pagina, comunicati
 *    dal browser alla chiusura. Chi non la comunica conta zero.
 *
 * Provenienza, paesi e dispositivi si contano per visita, non per pagina.
 */
final class Report
{
    public function __construct(
        public readonly Period $period,
        public readonly ?string $host = null,
    ) {}

    /** Il filtro comune: giorni del periodo e, se scelto, un solo sito. */
    private function base(?Period $period = null, bool $allSites = false): Builder
    {
        $period ??= $this->period;

        return DB::table('page_views')
            ->whereBetween('day', [$period->from->toDateString(), $period->to->toDateString()])
            ->when($this->host && ! $allSites, fn (Builder $query) => $query->where('host', $this->host));
    }

    private function entries(): Builder
    {
        return $this->base()->where('is_entry', true);
    }

    /* Schede in cima ----------------------------------------------------- */

    /**
     * Le sei schede, ognuna con il valore del periodo e il confronto
     * con il periodo precedente di pari durata.
     *
     * @return list<array{key: string, label: string, value: string, raw: float, previous: float, change: ?float, inverse: bool, note: string}>
     */
    public function summary(): array
    {
        $now = $this->totals($this->period);
        $before = $this->totals($this->period->previous());

        $card = fn (string $key, string $label, callable $format, string $note, bool $inverse = false) => [
            'key' => $key,
            'label' => $label,
            'value' => $format($now[$key]),
            'raw' => (float) $now[$key],
            'previous' => (float) $before[$key],
            'change' => $before[$key] > 0 ? ($now[$key] - $before[$key]) / $before[$key] : null,
            'inverse' => $inverse,
            'note' => $note,
        ];

        return [
            $card('visitors', 'Visitatori', fn ($v) => Format::number($v), 'persone distinte per giorno'),
            $card('sessions', 'Visite', fn ($v) => Format::number($v), 'sessioni di navigazione'),
            $card('views', 'Pagine viste', fn ($v) => Format::number($v), 'tutte le pagine aperte'),
            $card('pages_per_session', 'Pagine per visita', fn ($v) => Format::decimal($v), 'profondita\' della visita'),
            $card('avg_duration', 'Durata media', fn ($v) => Format::duration($v), 'tempo per visita'),
            $card('bounce_rate', 'Rimbalzo', fn ($v) => Format::percent($v), 'visite di una sola pagina', inverse: true),
        ];
    }

    /** @return array{visitors: int, sessions: int, views: int, pages_per_session: float, avg_duration: float, bounce_rate: float} */
    private function totals(Period $period): array
    {
        $row = $this->base($period)->selectRaw(
            'count(*) as views, count(distinct visitor) as visitors, '
            .'sum(case when is_entry = 1 then 1 else 0 end) as sessions, coalesce(sum(duration), 0) as seconds'
        )->first();

        $views = (int) ($row->views ?? 0);
        $sessions = (int) ($row->sessions ?? 0);

        return [
            'visitors' => (int) ($row->visitors ?? 0),
            'sessions' => $sessions,
            'views' => $views,
            'pages_per_session' => $sessions > 0 ? $views / $sessions : 0.0,
            'avg_duration' => $sessions > 0 ? (int) $row->seconds / $sessions : 0.0,
            'bounce_rate' => $sessions > 0 ? $this->bounced($period) / $sessions : 0.0,
        ];
    }

    /** Visite che hanno visto una sola pagina. */
    private function bounced(Period $period): int
    {
        return (int) $this->base($period)
            ->where('is_entry', true)
            ->joinSub($this->pagesPerSession($period), 's', 's.session_id', '=', 'page_views.session_id')
            ->where('s.pages', 1)
            ->count();
    }

    private function pagesPerSession(Period $period): Builder
    {
        return $this->base($period)->selectRaw('session_id, count(*) as pages')->groupBy('session_id');
    }

    /** Persone con una pagina vista negli ultimi cinque minuti, a prescindere dal periodo. */
    public function live(): int
    {
        return (int) DB::table('page_views')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->when($this->host, fn (Builder $query) => $query->where('host', $this->host))
            ->distinct()
            ->count('visitor');
    }

    /* Andamento ---------------------------------------------------------- */

    /**
     * Un punto per ora, giorno o mese, con zero dove non e' successo niente:
     * un grafico che salta i giorni vuoti racconta una continuita' che non c'e'.
     *
     * @return list<array{label: string, title: string, views: int, visitors: int, sessions: int}>
     */
    public function series(?string $granularity = null): array
    {
        $granularity ??= $this->period->granularity();
        $today = Period::today();

        if ($granularity === 'hour') {
            $rows = $this->base()
                ->selectRaw('day, hour, count(*) as views, count(distinct visitor) as visitors, sum(case when is_entry = 1 then 1 else 0 end) as sessions')
                ->groupBy('day', 'hour')->get()
                ->keyBy(fn ($row) => $row->day.' '.(int) $row->hour);

            $points = [];

            for ($day = $this->period->from; $day <= $this->period->to; $day = $day->addDay()) {
                for ($hour = 0; $hour < 24; $hour++) {
                    // Le ore di oggi che non sono ancora arrivate non sono "zero visite".
                    if ($day->equalTo($today) && $hour > (int) CarbonImmutable::now(config('ksm.analytics.timezone'))->format('G')) {
                        break 2;
                    }

                    $row = $rows[$day->toDateString().' '.$hour] ?? null;
                    $points[] = $this->point(sprintf('%02d', $hour), $day->translatedFormat('j M').', ore '.sprintf('%02d', $hour), $row);
                }
            }

            return $points;
        }

        $rows = $this->base()
            ->selectRaw('day, count(*) as views, count(distinct visitor) as visitors, sum(case when is_entry = 1 then 1 else 0 end) as sessions')
            ->groupBy('day')->get()->keyBy('day');

        if ($granularity === 'day') {
            $points = [];

            for ($day = $this->period->from; $day <= $this->period->to; $day = $day->addDay()) {
                $points[] = $this->point($day->translatedFormat('j M'), $day->translatedFormat('l j F Y'), $rows[$day->toDateString()] ?? null);
            }

            return $points;
        }

        // Mese: i visitatori di un mese sono la somma di quelli dei giorni (l'impronta cambia ogni notte).
        $months = [];

        foreach ($rows as $day => $row) {
            $key = substr($day, 0, 7);
            $months[$key] ??= (object) ['views' => 0, 'visitors' => 0, 'sessions' => 0];
            $months[$key]->views += (int) $row->views;
            $months[$key]->visitors += (int) $row->visitors;
            $months[$key]->sessions += (int) $row->sessions;
        }

        $points = [];

        for ($month = $this->period->from->startOfMonth(); $month <= $this->period->to; $month = $month->addMonth()) {
            $points[] = $this->point($month->translatedFormat('M y'), $month->translatedFormat('F Y'), $months[$month->format('Y-m')] ?? null);
        }

        return $points;
    }

    private function point(string $label, string $title, ?object $row): array
    {
        return [
            'label' => $label,
            'title' => $title,
            'views' => (int) ($row->views ?? 0),
            'visitors' => (int) ($row->visitors ?? 0),
            'sessions' => (int) ($row->sessions ?? 0),
        ];
    }

    /* Pagine ------------------------------------------------------------- */

    /**
     * Le pagine piu' viste. `avg_time` e' la media di chi ha comunicato una
     * durata: le pagine chiuse senza segnale non abbassano la media.
     *
     * @return Collection<int, object{path: string, views: int, visitors: int, entries: int, avg_time: float, share: float}>
     */
    public function pages(int $limit = 15): Collection
    {
        $total = max(1, (int) $this->base()->count());

        return $this->base()
            ->selectRaw('path, count(*) as views, count(distinct visitor) as visitors, '
                .'sum(case when is_entry = 1 then 1 else 0 end) as entries, avg(nullif(duration, 0)) as avg_time')
            ->groupBy('path')
            ->orderByDesc('views')->orderBy('path')
            ->limit($limit)->get()
            ->map(fn ($row) => (object) [
                'path' => $row->path,
                'views' => (int) $row->views,
                'visitors' => (int) $row->visitors,
                'entries' => (int) $row->entries,
                'avg_time' => (float) $row->avg_time,
                'share' => $row->views / $total,
            ]);
    }

    /**
     * Da quali pagine comincia la visita, e quante di quelle visite si
     * fermano li'.
     *
     * @return Collection<int, object{path: string, entries: int, bounces: int, bounce_rate: float}>
     */
    public function entryPages(int $limit = 10): Collection
    {
        return $this->entries()
            ->joinSub($this->pagesPerSession($this->period), 's', 's.session_id', '=', 'page_views.session_id')
            ->selectRaw('page_views.path as path, count(*) as entries, sum(case when s.pages = 1 then 1 else 0 end) as bounces')
            ->groupBy('page_views.path')
            ->orderByDesc('entries')->orderBy('page_views.path')
            ->limit($limit)->get()
            ->map(fn ($row) => (object) [
                'path' => $row->path,
                'entries' => (int) $row->entries,
                'bounces' => (int) $row->bounces,
                'bounce_rate' => $row->entries > 0 ? $row->bounces / $row->entries : 0.0,
            ]);
    }

    /* Provenienza -------------------------------------------------------- */

    /**
     * Le visite per canale, dal piu' frequente.
     *
     * @return Collection<int, object{key: string, label: string, count: int, share: float}>
     */
    public function channels(): Collection
    {
        return $this->grouped($this->entries(), 'channel', fn ($key) => TrafficSource::LABELS[$key ?? 'direct'] ?? 'Altro');
    }

    /**
     * Le singole fonti: Google, Facebook, un sito esterno, una campagna.
     *
     * @return Collection<int, object{source: string, channel: string, count: int, share: float}>
     */
    public function sources(int $limit = 12): Collection
    {
        $total = max(1, (int) $this->entries()->count());

        return $this->entries()
            ->selectRaw('channel, source, count(*) as total')
            ->groupBy('channel', 'source')
            ->orderByDesc('total')->orderBy('source')
            ->limit($limit)->get()
            ->map(fn ($row) => (object) [
                'source' => $row->source ?? 'Accesso diretto',
                'channel' => $row->channel ?? 'direct',
                'channel_label' => TrafficSource::LABELS[$row->channel ?? 'direct'] ?? 'Altro',
                'count' => (int) $row->total,
                'share' => $row->total / $total,
            ]);
    }

    /**
     * Le campagne con indirizzi utm_*.
     *
     * @return Collection<int, object{campaign: string, source: ?string, medium: ?string, count: int}>
     */
    public function campaigns(int $limit = 10): Collection
    {
        return $this->entries()
            ->whereNotNull('campaign')
            ->selectRaw('campaign, source, medium, count(*) as total')
            ->groupBy('campaign', 'source', 'medium')
            ->orderByDesc('total')->orderBy('campaign')
            ->limit($limit)->get()
            ->map(fn ($row) => (object) [
                'campaign' => $row->campaign,
                'source' => $row->source,
                'medium' => $row->medium,
                'count' => (int) $row->total,
            ]);
    }

    /* Pubblico ----------------------------------------------------------- */

    /** @return Collection<int, object{key: ?string, label: string, count: int, share: float}> */
    public function countries(int $limit = 10): Collection
    {
        return $this->grouped($this->entries(), 'country', fn ($key) => Format::country($key), $limit);
    }

    /** @return Collection<int, object{key: ?string, label: string, count: int, share: float}> */
    public function devices(): Collection
    {
        return $this->grouped($this->entries(), 'device', fn ($key) => Format::device((string) $key));
    }

    /** @return Collection<int, object{key: ?string, label: string, count: int, share: float}> */
    public function browsers(int $limit = 6): Collection
    {
        return $this->grouped($this->entries(), 'browser', fn ($key) => $key ?? 'Altro', $limit);
    }

    /** @return Collection<int, object{key: ?string, label: string, count: int, share: float}> */
    public function systems(int $limit = 6): Collection
    {
        return $this->grouped($this->entries(), 'os', fn ($key) => $key ?? 'Altro', $limit);
    }

    /**
     * Conta le righe per una colonna, con la quota sul totale.
     * Oltre il limite il resto si raccoglie in "Altri".
     */
    private function grouped(Builder $query, string $column, callable $label, ?int $limit = null): Collection
    {
        $rows = $query->selectRaw("$column as grp, count(*) as total")
            ->groupBy($column)->orderByDesc('total')->orderBy($column)->get();

        $all = max(1, (int) $rows->sum('total'));

        $items = $rows->map(fn ($row) => (object) [
            'key' => $row->grp,
            'label' => $label($row->grp),
            'count' => (int) $row->total,
            'share' => $row->total / $all,
        ]);

        if ($limit !== null && $items->count() > $limit) {
            $rest = $items->slice($limit);
            $items = $items->take($limit)->push((object) [
                'key' => '*',
                'label' => 'Altri',
                'count' => $rest->sum('count'),
                'share' => $rest->sum('count') / $all,
            ]);
        }

        return $items->values();
    }

    /* Quando ------------------------------------------------------------- */

    /**
     * Pagine viste per giorno della settimana (0 = lunedi') e ora.
     *
     * @return array{cells: list<list<int>>, max: int}
     */
    public function heatmap(): array
    {
        $cells = array_fill(0, 7, array_fill(0, 24, 0));

        foreach ($this->base()->selectRaw('day, hour, count(*) as total')->groupBy('day', 'hour')->get() as $row) {
            $weekday = CarbonImmutable::parse($row->day)->dayOfWeekIso - 1;
            $cells[$weekday][(int) $row->hour] += (int) $row->total;
        }

        return ['cells' => $cells, 'max' => max(1, ...array_map('max', $cells))];
    }

    /* Siti --------------------------------------------------------------- */

    /**
     * I siti della rete con piu' visite. Solo senza un sito scelto: il
     * confronto ha senso solo fra tanti.
     *
     * @return Collection<int, object{host: string, views: int, visitors: int, sessions: int}>
     */
    public function sites(int $limit = 20): Collection
    {
        return $this->base(allSites: true)
            ->selectRaw('host, count(*) as views, count(distinct visitor) as visitors, sum(case when is_entry = 1 then 1 else 0 end) as sessions')
            ->groupBy('host')
            ->orderByDesc('views')->orderBy('host')
            ->limit($limit)->get()
            ->map(fn ($row) => (object) [
                'host' => $row->host,
                'views' => (int) $row->views,
                'visitors' => (int) $row->visitors,
                'sessions' => (int) $row->sessions,
            ]);
    }

    /** @return list<string> gli host che hanno almeno una visita registrata, per la tendina */
    public static function hosts(): array
    {
        return DB::table('page_views')->distinct()->orderBy('host')->pluck('host')->all();
    }

    /** Ci sono visite nel periodo? Per mostrare un avviso al posto di una pagina di zeri. */
    public function isEmpty(): bool
    {
        return ! $this->base()->exists();
    }
}
