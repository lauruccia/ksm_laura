<?php

namespace App\Http\Middleware;

use App\Support\Ads\BotDetector;
use App\Support\Analytics\PageViewRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Conta le pagine pubbliche servite, per le statistiche in amministrazione.
 *
 * La riga si scrive dopo l'invio della risposta (terminate), cosi' chi
 * naviga non aspetta il database. Prima, qui, si decide solo se la
 * richiesta merita di essere contata e si lascia nella richiesta la sigla
 * che la pagina rimandera' con la durata della visita.
 *
 * Non si contano: richieste non-GET o in JSON, programmi automatici,
 * l'amministrazione quando naviga da loggata, chi ha attivo "Do Not Track" o
 * Global Privacy Control, anticipi del browser e le pagine con dati privati
 * nell'indirizzo (reimpostazione password, esiti dei pagamenti).
 */
class TrackPageView
{
    /** Percorsi che non vanno mai contati: aree private o indirizzi con codici. */
    private const EXCLUDED = '#^(amministrazione|area-azienda|area-inserzionista|account|attivazione|abbonamento'
        .'|webhook|tls|up|stat|banner|esci|verifica|password|traccia-ordine'
        .'|pagamento/(rientro|esito|annullato|riprova))(/|$)#';

    public const ATTRIBUTE = 'analytics.uid';

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldCount($request)) {
            $request->attributes->set(self::ATTRIBUTE, (string) Str::ulid());
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $uid = $request->attributes->get(self::ATTRIBUTE);

        if (! $uid || $response->getStatusCode() !== 200) {
            return;
        }

        // Solo pagine: immagini, XML della mappa del sito e risposte JSON restano fuori.
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return;
        }

        try {
            app(PageViewRecorder::class)->record($request, $uid);
        } catch (\Throwable $e) {
            // Le statistiche non devono mai rompere il sito.
            report($e);
        }
    }

    private function shouldCount(Request $request): bool
    {
        if (! config('ksm.analytics.enabled') || ! $request->isMethod('GET') || $request->expectsJson() || $request->ajax()) {
            return false;
        }

        if (preg_match(self::EXCLUDED, ltrim($request->path(), '/')) === 1) {
            return false;
        }

        if (config('ksm.analytics.respect_dnt') && ($request->headers->get('DNT') === '1' || $request->headers->get('Sec-GPC') === '1')) {
            return false;
        }

        // Pagina preparata in anticipo dal browser: nessuno la sta guardando.
        if (str_contains((string) ($request->headers->get('Sec-Purpose') ?? $request->headers->get('Purpose')), 'prefetch')
            || str_contains((string) $request->headers->get('Sec-Purpose'), 'prerender')) {
            return false;
        }

        if (BotDetector::isBot($request->userAgent())) {
            return false;
        }

        // Chi amministra il sito lo guarda spesso: le sue visite falserebbero i numeri.
        return ! $request->user()?->isAdmin();
    }
}
