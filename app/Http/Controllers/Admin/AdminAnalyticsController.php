<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Analytics\Humans;
use App\Support\Analytics\Insights;
use App\Support\Analytics\Period;
use App\Support\Analytics\Report;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Statistiche delle visite di tutti i siti della rete, o di uno solo.
 *
 * Una sola pagina con periodo e sito scelti dall'indirizzo (?periodo=30,
 * ?dal=&al=, ?sito=): ogni vista del report e' una query a se' sulla
 * tabella page_views, cosi' la pagina resta leggera anche con molti dati.
 */
class AdminAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $period = Period::fromRequest($request);
        $humans = $this->humans($request);
        $report = new Report($period, $this->host($request), $humans);

        // Ogni vista e' una scansione di decine di migliaia di righe: i numeri di un periodo si calcolano una volta ogni quarto d'ora.
        $key = 'admin:analytics:'.sha1($period->from->toDateString().'|'.$period->to->toDateString().'|'.$report->host.'|'.(int) $humans);

        if ($request->boolean('aggiorna')) {
            Cache::forget($key);
        }

        // Quanti minuti restano buoni i numeri gia' calcolati (?aggiorna=1 li ricalcola); 0 = sempre freschi.
        $minutes = (int) config('ksm.analytics.cache_minutes');
        $data = $minutes > 0
            ? Cache::remember($key, now()->addMinutes($minutes), fn () => $this->compute($period, $report, $humans))
            : $this->compute($period, $report, $humans);

        return view('admin.analytics.index', $data + [
            'period' => $period,
            'humans' => $humans,
            'report' => $report,
            'host' => $report->host,
            'hosts' => $this->hostOptions($report->host),
            'live' => $report->live(),
            'granularity' => $period->granularity(),
        ]);
    }

    /**
     * Tutti i numeri della pagina. Ogni blocco che supera il secondo finisce
     * nel log (storage/logs) con il suo nome: dice dove si perde il tempo.
     *
     * @return array<string, mixed>
     */
    private function compute(Period $period, Report $report, bool $humans): array
    {
        $deep = new Insights($period, $report->host, $humans);
        $none = collect();
        $slow = [];
        $started = microtime(true);

        $run = function (string $name, callable $query) use (&$slow) {
            $start = microtime(true);
            $result = $query();
            $ms = (int) round((microtime(true) - $start) * 1000);

            if ($ms >= 1000) {
                $slow[$name] = $ms;
            }

            return $result;
        };

        $empty = $report->isEmpty();
        $skip = fn ($fallback, string $name, callable $query) => $empty ? $fallback : $run($name, $query);

        $data = [
            'humanInfo' => $run('umani', fn () => Humans::summary($period, $report->host)),
            'empty' => $empty,
            'summary' => $run('schede', fn () => $report->summary()),
            'series' => $skip([], 'andamento', fn () => $report->series()),
            'pages' => $skip($none, 'pagine', fn () => $report->pages(15)),
            'entryPages' => $skip($none, 'pagine-ingresso', fn () => $report->entryPages(10)),
            'channels' => $skip($none, 'canali', fn () => $report->channels()),
            'sources' => $skip($none, 'fonti', fn () => $report->sources(12)),
            'campaigns' => $skip($none, 'campagne', fn () => $report->campaigns(10)),
            'countries' => $skip($none, 'paesi', fn () => $report->countries(10)),
            'devices' => $skip($none, 'dispositivi', fn () => $report->devices()),
            'browsers' => $skip($none, 'browser', fn () => $report->browsers(6)),
            'systems' => $skip($none, 'sistemi', fn () => $report->systems(6)),
            'heatmap' => $skip(null, 'mappa-oraria', fn () => $report->heatmap()),
            'sites' => $report->host === null && ! $empty ? $run('siti', fn () => $report->sites(20)) : $none,

            // Piu' a fondo.
            'goals' => $run('obiettivi', fn () => $deep->goals()),
            'funnel' => $skip([], 'percorso', fn () => $deep->funnel()),
            'conversionSources' => $skip($none, 'obiettivi-per-fonte', fn () => $deep->conversionSources()),
            'returningEnabled' => $deep->returningEnabled(),
            'audience' => $skip(null, 'pubblico', fn () => $deep->audience()),
            'behaviour' => $skip([], 'comportamento', fn () => $deep->audienceBehaviour()),
            // Le coorti rileggono otto settimane di visite: senza il cookie dei visitatori di ritorno sarebbero sempre vuote.
            'cohorts' => $empty || ! $deep->returningEnabled() ? [] : $run('coorti', fn () => $deep->cohorts()),
            'companies' => $skip($none, 'aziende', fn () => $deep->companies()),
            'products' => $skip($none, 'prodotti', fn () => $deep->products()),
            'storefronts' => $skip($none, 'vetrine', fn () => $deep->storefronts()),
            'searches' => $skip($none, 'ricerche', fn () => $deep->searches()),
            'keywords' => $skip($none, 'parole-chiave', fn () => $deep->keywords()),
            'exitPages' => $skip($none, 'uscite', fn () => $deep->exitPages()),
            'regions' => $skip($none, 'regioni', fn () => $deep->regions()),
            'cities' => $skip($none, 'citta', fn () => $deep->cities()),
            'hasPlaces' => $empty ? false : $run('luoghi', fn () => $deep->hasPlaces()),
        ];

        arsort($slow);
        $data['computed'] = ['at' => now()->toIso8601String(), 'seconds' => round(microtime(true) - $started, 1), 'slow' => $slow];

        if ($slow !== []) {
            Log::info('Statistiche: blocchi lenti (millisecondi)', ['sito' => $report->host, 'dal' => $period->from->toDateString(), 'al' => $period->to->toDateString(), 'umani' => $humans] + $slow);
        }

        return $data;
    }

    /** Tutto su una pagina sola: da dove si arriva, dove si va dopo, quanto ci si ferma. */
    public function page(Request $request): View
    {
        $period = Period::fromRequest($request);
        $path = '/'.ltrim(mb_substr((string) $request->query('path', '/'), 0, 255), '/');
        $humans = $this->humans($request);
        $insights = new Insights($period, $this->host($request), $humans);

        return view('admin.analytics.page', [
            'period' => $period,
            'humans' => $humans,
            'host' => $insights->host,
            'path' => $path,
            'page' => $insights->page($path),
        ]);
    }

    /**
     * Gli stessi numeri della pagina in un CSV che Excel apre in italiano:
     * punto e virgola, virgola decimale, UTF-8 con BOM.
     */
    public function export(Request $request, string $type): StreamedResponse
    {
        $period = Period::fromRequest($request);
        $report = new Report($period, $this->host($request), $this->humans($request));
        $name = 'statistiche-'.$type.'-'.$period->from->format('Ymd').'-'.$period->to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($report, $type) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $put = fn (array $row) => fputcsv($out, $row, ';');

            match ($type) {
                'giorni' => $this->days($report, $put),
                'pagine' => $this->pages($report, $put),
                default => $this->sources($report, $put),
            };

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function days(Report $report, callable $put): void
    {
        $put(['Giorno', 'Visitatori', 'Visite', 'Pagine viste']);

        foreach ($report->series('day') as $point) {
            $put([$point['title'], $point['visitors'], $point['sessions'], $point['views']]);
        }
    }

    private function pages(Report $report, callable $put): void
    {
        $put(['Pagina', 'Pagine viste', 'Visitatori', 'Visite iniziate qui', 'Tempo medio (secondi)']);

        foreach ($report->pages(5000) as $page) {
            $put([$page->path, $page->views, $page->visitors, $page->entries, number_format($page->avg_time, 0, ',', '')]);
        }
    }

    private function sources(Report $report, callable $put): void
    {
        $put(['Canale', 'Fonte', 'Visite']);

        foreach ($report->sources(1000) as $source) {
            $put([$source->channel_label, $source->source, $source->count]);
        }
    }

    /** Di serie solo il traffico probabilmente umano; ?umani=no mostra tutto. */
    private function humans(Request $request): bool
    {
        return ! in_array(strtolower((string) $request->query('umani')), ['no', '0', 'false', 'tutto'], true);
    }

    /** Il sito scelto, solo se l'indirizzo e' fatto come un host. */
    private function host(Request $request): ?string
    {
        $host = strtolower(trim((string) $request->query('sito')));

        return preg_match('/^[a-z0-9.\-]{1,190}$/', $host) === 1 ? $host : null;
    }

    /**
     * Gli host fra cui scegliere: quelli con visite, piu' quello gia'
     * selezionato anche se in questo periodo non ne ha.
     *
     * @return list<string>
     */
    private function hostOptions(?string $selected): array
    {
        $hosts = Report::hosts();

        if ($selected !== null && ! in_array($selected, $hosts, true)) {
            $hosts[] = $selected;
            sort($hosts);
        }

        return $hosts;
    }
}
