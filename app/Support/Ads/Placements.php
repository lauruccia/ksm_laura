<?php

namespace App\Support\Ads;

/**
 * Posizioni in cui compare un banner.
 *
 * Le chiavi sono quelle del sito originale, cosi' le campagne importate
 * restano valide. Un banner si mostra in una posizione solo se la vista la
 * dichiara con <x-ad-slot>.
 */
final class Placements
{
    public const HOME_BELOW_HEADER = 'home_below_header';

    public const HOME_UNDER_FEATURED = 'home_under_featured_companies';

    public const HOME_ABOVE_FOOTER = 'home_above_footer';

    public const HOME_POPUP = 'home_popups';

    public const COMPANIES_LIST = 'store_list_above_stores';

    public const PRODUCTS_LIST = 'products_above_products';

    public const PRODUCT_DETAIL = 'product_details_above_product_detail';

    /** @return array<string, string> chiave => etichetta */
    public static function all(): array
    {
        return [
            self::HOME_BELOW_HEADER => 'Home, sotto la ricerca',
            self::HOME_UNDER_FEATURED => 'Home, sotto le aziende in evidenza',
            self::HOME_ABOVE_FOOTER => 'Home, sopra il piede',
            self::HOME_POPUP => 'Home, popup',
            self::COMPANIES_LIST => 'Elenco aziende, sopra le aziende',
            self::PRODUCTS_LIST => 'Catalogo, sopra i prodotti',
            self::PRODUCT_DETAIL => 'Scheda prodotto, in cima',
        ];
    }

    public static function label(string $key): string
    {
        return self::all()[$key] ?? $key;
    }
}
