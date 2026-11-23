<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Testi scritti a mano per una pagina /categorie/... : titolo, testo
 * sopra l'elenco, testo sotto, titolo e descrizione per i motori.
 *
 * Quello che resta vuoto lo scrive il sito da solo.
 */
class CategoryLandingText extends Model
{
    /** place_key del modello valido per tutti i luoghi di una categoria. */
    public const ALL_PLACES = '*';

    /** I campi di testo, nell'ordine del modulo. */
    public const FIELDS = ['heading', 'meta_title', 'meta_description', 'intro', 'body'];

    protected $fillable = ['company_category_id', 'place_key', ...self::FIELDS];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CompanyCategory::class, 'company_category_id');
    }
}
