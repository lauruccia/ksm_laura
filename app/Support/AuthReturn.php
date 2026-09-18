<?php

namespace App\Support;

/**
 * Dove si torna dopo l'accesso o la registrazione fatti dentro l'acquisto.
 *
 * Si accettano solo chiavi note, mai un indirizzo scritto nel modulo:
 * cosi' nessuno puo' far rimbalzare l'acquirente fuori dal sito.
 */
class AuthReturn
{
    /** Chiave nel modulo => rotta dove tornare. */
    private const DESTINATIONS = [
        'pagamento' => 'checkout.show',
        'carrello' => 'cart.index',
    ];

    public static function known(?string $key): bool
    {
        return isset(self::DESTINATIONS[$key]);
    }

    public static function url(?string $key, string $fallback): string
    {
        return self::known($key) ? route(self::DESTINATIONS[$key]) : $fallback;
    }
}
