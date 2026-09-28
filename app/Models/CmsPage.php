<?php

namespace App\Models;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsPage extends Model
{
    protected $fillable = [
        'domain_id', 'title', 'slug', 'content', 'status', 'locations', 'visibility', 'banner_image',
        'meta_title', 'meta_description', 'meta_keywords', 'canonical_url',
        'sort_order', 'include_in_sitemap', 'published_at',
    ];

    protected $casts = [
        'locations' => 'array',
        'include_in_sitemap' => 'boolean',
        'published_at' => 'datetime',
    ];

    /** Il dominio della rete a cui appartiene; null e' il sito principale. */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Solo le pagine del sito corrente: quelle di un dominio della rete sul
     * dominio, quelle di KSM sul sito principale, nessuna sul dominio di
     * un'azienda (il sito e' la sua pagina).
     */
    public function scopeForSite($query, ?TenantContext $tenant = null)
    {
        $tenant ??= app(TenantContext::class);

        return match (true) {
            $tenant->isCompanySite() => $query->whereRaw('1 = 0'),
            $tenant->isNetworkSite() => $query->where('domain_id', $tenant->domain()->getKey()),
            default => $query->whereNull('domain_id'),
        };
    }

    /** Per l'elenco in amministrazione. */
    public function getSiteNameAttribute(): string
    {
        return $this->domain?->domain ?? 'Sito principale';
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeInLocation($query, string $location)
    {
        return $query->whereJsonContains('locations', $location);
    }
}
