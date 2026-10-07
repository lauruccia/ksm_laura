<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una pagina vista sul sito pubblico. Le righe le scrive PageViewRecorder,
 * la durata la aggiorna la pagina stessa quando si chiude (vedi analytics.js).
 */
class PageView extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    // `day` resta testo "2026-10-05": un cast a data lo scriverebbe con l'ora, e i raggruppamenti per giorno non tornerebbero.
    protected $casts = [
        'is_entry' => 'boolean',
        'duration' => 'integer',
        'created_at' => 'datetime',
    ];
}
