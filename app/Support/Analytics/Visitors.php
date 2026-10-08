<?php

namespace App\Support\Analytics;

use App\Support\Ads\BotDetector;
use Illuminate\Http\Request;

/**
 * Chi si puo' contare e come si riconosce.
 *
 * Le regole stanno qui, in un posto solo, perche' le usano sia le pagine
 * viste sia gli obiettivi: se una visita non si conta, non si conta nemmeno
 * l'ordine che ne segue.
 */
final class Visitors
{
    /** Cookie facoltativo dei visitatori di ritorno. */
    public const COOKIE = 'ksm_vid';

    public static function countable(Request $request): bool
    {
        if (! config('ksm.analytics.enabled')) {
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

    /** Impronta della persona per un giorno: cambia ogni notte, non si risale all'IP. */
    public static function hash(Request $request, string $day): string
    {
        return sha1(implode('|', [
            (string) config('app.key'),
            $day,
            (string) $request->ip(),
            (string) $request->userAgent(),
        ]));
    }

    /** Impronta del cookie di ritorno (il valore del cookie non si salva mai in chiaro). */
    public static function cookieHash(string $value): string
    {
        return sha1((string) config('app.key').'|vid|'.$value);
    }
}
