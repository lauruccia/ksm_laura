<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Difesa dei moduli contatti dai programmi che li compilano da soli.
 *
 * Tre segnali, nessuno visibile a chi scrive davvero:
 *  - un campo trappola (`website`), nascosto: una persona non lo vede e lo
 *    lascia vuoto, un programma che compila tutto lo riempie;
 *  - un gettone con l'ora in cui il modulo e' stato aperto (`_cg`, cifrato): chi
 *    invia senza averlo aperto, o in meno di due secondi, e' un programma;
 *  - troppi link nel testo, tipico dello spam.
 *
 * Il limite di invii per indirizzo sta nelle rotte (throttle).
 */
final class ContactGuard
{
    /** Secondi minimi fra l'apertura del modulo e l'invio. */
    public const MIN_SECONDS = 2;

    /** Dopo tanti giorni il modulo aperto non e' piu' valido e va ricaricato. */
    public const MAX_DAYS = 7;

    public const FIELD = 'website';

    public const TOKEN = '_cg';

    public const TRAP = 'trap';

    public const EXPIRED = 'expired';

    public static function token(?int $issuedAt = null): string
    {
        return Crypt::encryptString((string) ($issuedAt ?? time()));
    }

    /** I campi che un invio regolare porta con se': per i test. */
    public static function passing(): array
    {
        return [self::FIELD => '', self::TOKEN => self::token(time() - 60)];
    }

    /**
     * Null se l'invio e' regolare. 'trap' per lo spam da scartare in silenzio
     * (al programma si risponde "inviato"), 'expired' per un modulo vecchio o
     * troppo veloce (a chi e' una persona si chiede di riprovare).
     */
    public static function inspect(Request $request): ?string
    {
        if (filled($request->input(self::FIELD))) {
            return self::TRAP;
        }

        if (preg_match_all('#https?://|www\.#i', (string) $request->input('message')) >= 3) {
            return self::TRAP;
        }

        try {
            $issued = (int) Crypt::decryptString((string) $request->input(self::TOKEN));
        } catch (DecryptException) {
            return self::EXPIRED;
        }

        $age = time() - $issued;

        return $age < self::MIN_SECONDS || $age > self::MAX_DAYS * 86400 ? self::EXPIRED : null;
    }
}
