<?php

namespace App\Http\Middleware;

use App\Support\Analytics\PageViewRecorder;
use App\Support\Analytics\Visitors;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
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

    /** Impronta del cookie di ritorno e se e' stato appena assegnato (solo con KSM_ANALYTICS_RETURNING). */
    public const VID = 'analytics.vid';

    public const VID_NEW = 'analytics.vid_new';

    public function handle(Request $request, Closure $next): Response
    {
        $cookie = null;

        if ($this->shouldCount($request)) {
            $request->attributes->set(self::ATTRIBUTE, (string) Str::ulid());
            $cookie = $this->returningCookie($request);
        }

        $response = $next($request);

        // Il cookie parte solo con una pagina vera, non con un reindirizzamento o un'immagine.
        if ($cookie && $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    /**
     * Visitatori di ritorno: se acceso in configurazione, un cookie anonimo
     * (numero casuale, nessun dato personale) dura 13 mesi. Ne salviamo solo
     * l'impronta. Spento di default: senza, non esiste nessun cookie.
     */
    private function returningCookie(Request $request): ?Cookie
    {
        if (! config('ksm.analytics.returning_visitors')) {
            return null;
        }

        $value = $request->cookies->get(Visitors::COOKIE);
        $isNew = ! is_string($value) || preg_match('/^[a-f0-9]{32}$/', $value) !== 1;

        if ($isNew) {
            $value = bin2hex(random_bytes(16));
        }

        $request->attributes->set(self::VID, Visitors::cookieHash($value));
        $request->attributes->set(self::VID_NEW, $isNew);

        // Si rinnova a ogni visita: chi torna ogni tanto non scade mai.
        return cookie(Visitors::COOKIE, $value, 60 * 24 * 395, '/', null, $request->isSecure(), true, false, 'lax');
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
        if (! $request->isMethod('GET') || $request->expectsJson() || $request->ajax()) {
            return false;
        }

        if (preg_match(self::EXCLUDED, ltrim($request->path(), '/')) === 1) {
            return false;
        }

        return Visitors::countable($request);
    }
}
