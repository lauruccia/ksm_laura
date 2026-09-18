<?php

namespace App\Support;

use App\Models\CompanyCategory;

/**
 * L'icona che rappresenta il settore di un'azienda nel biglietto.
 *
 * Le icone stanno sulle categorie principali; una sottocategoria prende
 * quella della prima madre che ne ha una. Le chiavi sono gli slug, che
 * restano uguali anche se l'amministratore ritocca il nome.
 */
final class CategoryIcon
{
    public const FALLBACK = 'tag';

    /** slug della categoria => nome dell'icona in <x-icon> */
    private const BY_SLUG = [
        'arte-e-intrattenimento' => 'palette',
        'artigiani' => 'hammer',
        'auto-e-moto' => 'car',
        'costruire-e-abitare' => 'home',
        'dormire' => 'bed',
        'elettronica-e-tecnologia' => 'monitor',
        'mangiare-e-bere' => 'utensils',
        'pet-shop' => 'paw',
        'professionisti' => 'briefcase',
        'regali-e-preziosi' => 'gift',
        'salute-e-bellezza' => 'heart',
        'servizi' => 'wrench',
        'vestire-e-camminare' => 'shirt',
        'sport' => 'ball',
        'altro' => 'grid',
    ];

    /**
     * Risale dalla categoria verso la radice. Le madri vanno caricate
     * prima (`category.parent.parent`), altrimenti ogni gradino e' una query.
     */
    public static function for(?CompanyCategory $category): string
    {
        $seen = [];

        for ($current = $category; $current !== null; $current = $current->parent) {
            // Un giro chiuso rimasto nei dati vecchi non manda in loop.
            if (in_array($current->id, $seen, true)) {
                break;
            }

            if (isset(self::BY_SLUG[$current->slug])) {
                return self::BY_SLUG[$current->slug];
            }

            $seen[] = $current->id;
        }

        return self::FALLBACK;
    }
}
