<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
{
    protected $fillable = [
        'website_name', 'company_name', 'vat_number',
        'website_url', 'website_email', 'support_email',
        'site_logo', 'favicon', 'hero_image', 'contact_number', 'address', 'about', 'header_tagline', 'header_subline',
        'location_map_embed', 'social_links', 'menus', 'base_shipping_rate', 'per_kg_rate',
    ];

    protected $casts = [
        'social_links' => 'array',
        'menus' => 'array',
    ];

    /** Riga unica di configurazione del sito. */
    public static function current(): self
    {
        return static::firstOrCreate([], ['website_name' => 'KSM']);
    }
}
