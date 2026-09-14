<?php

namespace App\Support;

/**
 * Elenco chiuso di cio' che un piano puo' concedere.
 *
 * Le chiavi sono quelle salvate in `plans.capabilities`; l'amministratore
 * le spunta una per una sul piano. Chi deve sapere se un'azienda puo' fare
 * qualcosa chiede a `Company::allows()`, mai al nome del piano.
 */
final class PlanCapabilities
{
    public const DIRECTORY = 'directory';

    public const CONTACT_CARD = 'contact_card';

    public const LOGO = 'logo';

    public const BANNER = 'banner';

    public const FEATURED = 'featured';

    public const SHOWCASE = 'showcase';

    public const GALLERY = 'gallery';

    public const DESCRIPTION = 'description';

    public const SHOP = 'shop';

    public const REVIEWS = 'reviews';

    public const CUSTOM_DOMAIN = 'custom_domain';

    /** @return array<string, string> chiave => etichetta mostrata in amministrazione */
    public static function all(): array
    {
        return [
            self::DIRECTORY => 'Presenza nella directory',
            self::CONTACT_CARD => 'Scheda contatti (indirizzo, telefono, email, sito)',
            self::LOGO => 'Logo',
            self::BANNER => 'Banner',
            self::FEATURED => 'Profilo in evidenza',
            self::SHOWCASE => 'Vetrina completa',
            self::GALLERY => 'Galleria offerte',
            self::DESCRIPTION => 'Descrizione estesa',
            self::SHOP => 'Vendita di prodotti e servizi nello shop',
            self::REVIEWS => 'Recensioni sul profilo',
            self::CUSTOM_DOMAIN => 'Dominio personalizzato',
        ];
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function label(string $key): string
    {
        return self::all()[$key] ?? $key;
    }

    /** Scarta le chiavi che non esistono piu': l'elenco lo decide il codice. */
    public static function sanitize(array $keys): array
    {
        return array_values(array_intersect(self::keys(), array_map('strval', $keys)));
    }
}
