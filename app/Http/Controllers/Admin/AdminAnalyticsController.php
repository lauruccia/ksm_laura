<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Analytics\Period;
use App\Support\Analytics\Report;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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
        $report = new Report($period, $this->host($request));

        $empty = $report->isEmpty();

        return view('admin.analytics.index', [
            'period' => $period,
            'report' => $report,
            'host' => $report->host,
            'hosts' => $this->hostOptions($report->host),
            'empty' => $empty,
            'live' => $report->live(),
            'summary' => $report->summary(),
            'series' => $empty ? [] : $report->series(),
            'granularity' => $period->granularity(),
            'pages' => $empty ? collect() : $report->pages(15),
            'entryPages' => $empty ? collect() : $report->entryPages(10),
            'channels' => $empty ? collect() : $report->channels(),
            'sources' => $empty ? collect() : $report->sources(12),
            'campaigns' => $empty ? collect() : $report->campaigns(10),
            'countries' => $empty ? collect() : $report->countries(10),
            'devices' => $empty ? collect() : $report->devices(),
            'browsers' => $empty ? collect() : $report->browsers(6),
            'systems' => $empty ? collect() : $report->systems(6),
            'heatmap' => $empty ? null : $report->heatmap(),
            'sites' => $report->host === null && ! $empty ? $report->sites(20) : collect(),
        ]);
    }

    /**
     * Gli stessi numeri della pagina in un CSV che Excel apre in italiano:
     * punto e virgola, virgola decimale, UTF-8 con BOM.
     */
    public function export(Request $request, string $type): StreamedResponse
    {
        $period = Period::fromRequest($request);
        $report = new Report($period, $this->host($request));
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
