<?php

namespace App\Support\Analytics;

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
        $visitor = $this->visitor($request, $local->toDateString());

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

        return PageView::create([
            'uid' => $uid,
            'host' => $host,
            'site_type' => $siteType,
            'site_id' => $siteId,
            'path' => $this->path($request),
            'session_id' => $session ?? (string) Str::ulid(),
            'visitor' => $visitor,
            'is_entry' => $isEntry,
            'channel' => $source['channel'] ?? null,
            'source' => $source['source'] ?? null,
            'medium' => $source['medium'] ?? null,
            'campaign' => $source['campaign'] ?? null,
            'country' => $isEntry ? Geo::country($request) : null,
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

    private function visitor(Request $request, string $day): string
    {
        return sha1(implode('|', [
            (string) config('app.key'),
            $day,
            (string) $request->ip(),
            (string) $request->userAgent(),
        ]));
    }
}
