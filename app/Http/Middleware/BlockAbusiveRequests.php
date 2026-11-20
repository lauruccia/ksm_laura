<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scudo per le richieste che non sono persone: scansioni di vulnerabilita',
 * strumenti di attacco, scansioni dei motori SEO commerciali e chi martella
 * il sito.
 *
 * Va prima della sessione e di tutto il resto: una richiesta respinta qui non
 * apre sessioni, non tocca il database e non finisce nelle statistiche.
 *
 * Regole, tutte spegnibili da config/ksm.php ('shield'):
 *  - indirizzi con codice dentro (tag HTML, SQL, risalite di cartella, ecc.): 403;
 *  - User-Agent di strumenti d'attacco o di scansioni SEO commerciali: 403,
 *    solo per le pagine (GET/HEAD): webhook e API di gestione non si toccano;
 *  - piu' di `rate_per_minute` pagine al minuto dallo stesso IP: 429.
 *
 * Non si bloccano mai: i motori di ricerca veri, gli User-Agent vuoti (alcuni
 * controlli di disponibilita' non ne mandano) e robots.txt.
 */
class BlockAbusiveRequests
{
    /** Percorsi di macchine e servizi: niente controllo sull'User-Agent ne' sul ritmo. */
    private const MACHINE_PATHS = '#^(webhook|management|tls|up|robots\.txt)(/|$)#';

    /** Aree dietro l'accesso: nessun limite di ritmo (ci lavora chi amministra). */
    private const PRIVATE_PATHS = '#^(amministrazione|area-azienda|area-inserzionista|account|abbonamento|pagamento)(/|$)#';

    /** Strumenti di attacco e scansioni commerciali che non portano visite vere. */
    private const BAD_AGENTS = '/sqlmap|nikto|nmap|masscan|zgrab|nuclei|acunetix|wpscan|dirbuster|gobuster|havij|netsparker|openvas|w3af'
        .'|ahrefsbot|semrushbot|mj12bot|dotbot|petalbot|bytespider|dataforseobot|blexbot|serpstatbot|zoominfobot|megaindex|seekport/i';

    /** Codice dentro un indirizzo: mai in una ricerca o in un filtro veri. */
    private const PAYLOADS = [
        '/<\s*\/?\s*[a-z!?]/i',                       // tag HTML
        '/javascript\s*:|vbscript\s*:|data\s*:text\/html/i',
        '/\bunion\b.{0,30}\bselect\b/i',
        '/\bselect\b.{0,40}\bfrom\b.{0,40}\b(information_schema|users|mysql)\b/i',
        '/information_schema|\bsleep\s*\(\s*\d|\bbenchmark\s*\(|\bwaitfor\s+delay\b|\bload_file\s*\(/i',
        '/[\'"]\s*\b(or|and)\b\s*[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+/i',  // ' or 1=1
        '/\.\.[\/\\\\]/',                             // risalita di cartella
        '#/etc/passwd|/proc/self|boot\.ini|wp-config|\.env\b|\.git/#i',
        '/\$\{\s*jndi\s*:/i',                         // log4shell
        '/\\\\x[0-9a-f]{2}|\\\\0[0-7]{2}/i',          // \x27 \047 scritti a mano
        '/&#x?[0-9a-f]+;|&(lt|gt|quot|apos);/i',      // entita' HTML dentro un indirizzo
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ksm.shield.enabled')) {
            return $next($request);
        }

        $path = ltrim($request->path(), '/');

        if ($this->hasPayload($request)) {
            return $this->refuse($request, 403, 'codice nell\'indirizzo');
        }

        if (preg_match(self::MACHINE_PATHS, $path) === 1) {
            return $next($request);
        }

        $isPage = $request->isMethod('GET') || $request->isMethod('HEAD');

        if ($isPage && preg_match(self::BAD_AGENTS, (string) $request->userAgent()) === 1) {
            return $this->refuse($request, 403, 'User-Agent da strumento di scansione');
        }

        if ($isPage && preg_match(self::PRIVATE_PATHS, $path) !== 1 && ($retry = $this->tooFast($request)) !== null) {
            return $this->refuse($request, 429, 'troppe pagine al minuto', $retry);
        }

        return $next($request);
    }

    private function hasPayload(Request $request): bool
    {
        // Percorso e parametri come li ha scritti chi chiama, decodificati piu' volte: gli scanner raddoppiano le codifiche (%2527).
        $text = (string) $request->getRequestUri();

        for ($i = 0; $i < 3; $i++) {
            $decoded = rawurldecode($text);

            if ($decoded === $text) {
                break;
            }

            $text = $decoded;
        }

        $text = mb_substr($text, 0, 4000);

        foreach (self::PAYLOADS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @return int|null secondi da aspettare, se l'IP ha superato il limite */
    private function tooFast(Request $request): ?int
    {
        $max = (int) config('ksm.shield.rate_per_minute');

        if ($max <= 0) {
            return null;
        }

        try {
            // Cache a file: non aggiunge una scrittura sul database a ogni pagina.
            $limiter = new RateLimiter(Cache::store(config('ksm.shield.store')));
            $key = 'shield:'.sha1((string) $request->ip());

            if ($limiter->tooManyAttempts($key, $max)) {
                return max(1, $limiter->availableIn($key));
            }

            $limiter->hit($key, 60);
        } catch (\Throwable $e) {
            // Se la cache non risponde meglio lasciar passare che rompere il sito.
            report($e);
        }

        return null;
    }

    private function refuse(Request $request, int $status, string $reason, ?int $retryAfter = null): Response
    {
        // Uno scanner manda centinaia di richieste: il log registra solo i primi di ogni minuto per IP.
        try {
            if (Cache::store(config('ksm.shield.store'))->add('shield:log:'.sha1((string) $request->ip().$status), true, 60)) {
                Log::info('Scudo: richiesta respinta', [
                    'stato' => $status,
                    'motivo' => $reason,
                    'host' => $request->getHost(),
                    'indirizzo' => mb_substr($request->getRequestUri(), 0, 200),
                    'ip' => $request->ip(),
                ]);
            }
        } catch (\Throwable) {
            // Il log non deve mai bloccare la risposta.
        }

        $headers = ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store'];

        if ($retryAfter !== null) {
            $headers['Retry-After'] = (string) $retryAfter;
        }

        return response($status === 429 ? "Troppe richieste: riprova fra poco.\n" : "Richiesta non consentita.\n", $status, $headers);
    }
}
