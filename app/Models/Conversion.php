<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un obiettivo raggiunto da un visitatore: vedi App\Support\Analytics\Conversions. */
class Conversion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    // Come per PageView: `day` resta testo, senza cast a data.
    protected $casts = [
        'value' => 'decimal:2',
        'created_at' => 'datetime',
    ];
}
