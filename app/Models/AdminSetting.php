<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
{
    protected $fillable = [
        'website_name', 'company_name', 'vat_number',
        'website_url', 'website_email', 'support_email',
        'site_logo', 'favicon', 'contact_number', 'address', 'about',
        'location_map_embed', 'social_links', 'base_shipping_rate', 'per_kg_rate',
    ];

    protected $casts = [
        'social_links' => 'array',
    ];

    /** Riga unica di configurazione del sito. */
    public static function current(): self
    {
        return static::firstOrCreate([], ['website_name' => 'KSM']);
    }
}
