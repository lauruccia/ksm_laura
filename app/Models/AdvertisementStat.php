<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Visualizzazioni e clic di una campagna in un giorno. Le righe le scrive AdServer. */
class AdvertisementStat extends Model
{
    protected $fillable = ['advertisement_id', 'day', 'impressions', 'clicks', 'filtered_impressions', 'filtered_clicks'];

    protected $casts = [
        'day' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'filtered_impressions' => 'integer',
        'filtered_clicks' => 'integer',
    ];

    public function advertisement(): BelongsTo
    {
        return $this->belongsTo(Advertisement::class);
    }

    public function ctr(): float
    {
        return $this->impressions > 0 ? round($this->clicks / $this->impressions * 100, 2) : 0.0;
    }
}
