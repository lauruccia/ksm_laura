<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    protected $fillable = [
        'title', 'slug', 'content', 'status', 'locations', 'visibility', 'banner_image',
        'meta_title', 'meta_description', 'meta_keywords', 'canonical_url',
        'sort_order', 'include_in_sitemap', 'published_at',
    ];

    protected $casts = [
        'locations' => 'array',
        'include_in_sitemap' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeInLocation($query, string $location)
    {
        return $query->whereJsonContains('locations', $location);
    }
}
