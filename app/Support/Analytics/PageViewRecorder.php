<?php

namespace App\Support\Analytics;

use App\Http\Middleware\TrackPageView;
use App\Models\PageView;
use App\Support\Sites\HostDirectory;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Scrive nel database la pagina appena servita.
 *
 * Privacy: l'indirizzo IP e lo User-Agent non si salvano. Servono solo per
 * calcolare `visitor`, un'impronta (SHA-1 con la chiave dell'applicazione e
 * il giorno) che cambia ogni notte: dentro lo stesso giorno la stessa
 * persona e' riconoscibile, il giorno dopo no. Non serve nessun cookie.
 */
final class PageViewRecorder
{
    /** Dopo tanti minuti senza pagine, la visita successiva e' una visita nuova. */
    public const SESSION_MINUTES = 30;

    public function __construct(private readonly TenantContext $tenant) {}

    public function record(Request $request, string $uid): PageView
    {
        $host = HostDirectory::normalise($request->getHost());
        $local = Carbon::now(config('ksm.analytics.timezone'));
        $visitor = Visitors::hash($request, $local->toDateString());

        $session = PageView::query()
            ->where('visitor', $visitor)
            ->where('host', $host)
            ->where('created_at', '>=', now()->subMinutes(self::SESSION_MINUTES))
            ->orderByDesc('id')
            ->value('session_id');

        $isEntry = $session === null;
        $agent = UserAgent::parse($request->userAgent());
        $source = $isEntry
            ? TrafficSource::classify($request->headers->get('referer'), $request->query(), $host)
            : null;

        [$siteType, $siteId] = match (true) {
            $this->tenant->domain() !== null => ['domain', $this->tenant->domain()->getKey()],
            $this->tenant->company() !== null => ['company', $this->tenant->company()->getKey()],
            default => ['platform', null],
        };

        $geo = $isEntry ? Geo::locate($request) : ['country' => null, 'region' => null, 'city' => null];

        // Cookie facoltativo: la visita e' "di ritorno" se la stessa impronta e' gia' comparsa in un giorno precedente.
        $vid = $request->attributes->get(TrackPageView::VID);
        $returning = null;

        if ($isEntry && $vid) {
            $returning = ! $request->attributes->get(TrackPageView::VID_NEW)
                && PageView::query()->where('vid', $vid)->where('day', '<', $local->toDateString())->exists();
        }

        return PageView::create([
            'uid' => $uid,
            'host' => $host,
            'site_type' => $siteType,
            'site_id' => $siteId,
            'path' => $this->path($request),
            'search' => TrafficSource::term($request->query('cerca')),
            'session_id' => $session ?? (string) Str::ulid(),
            'visitor' => $visitor,
            'vid' => $vid,
            'is_entry' => $isEntry,
            'is_returning' => $returning,
            'channel' => $source['channel'] ?? null,
            'source' => $source['source'] ?? null,
            'medium' => $source['medium'] ?? null,
            'campaign' => $source['campaign'] ?? null,
            'keyword' => $source['keyword'] ?? null,
            'country' => $geo['country'],
            'region' => $geo['region'],
            'city' => $geo['city'],
            'device' => $agent['device'],
            'browser' => $agent['browser'],
            'os' => $agent['os'],
            'day' => $local->toDateString(),
            'hour' => (int) $local->format('G'),
        ]);
    }

    /** Solo il percorso: senza parametri, che possono portare indirizzi email o codici. */
    private function path(Request $request): string
    {
        $path = '/'.ltrim($request->decodedPath(), '/');
        $path = preg_replace('/[\x00-\x1F\x7F]+/u', '', $path) ?? $path;

        return mb_substr($path, 0, 255);
    }
}
