<?php

namespace App\Models;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CmsPage extends Model
{
    /** Valore di `sites` che indica il sito principale; gli altri sono id di domini. */
    public const PLATFORM = 'platform';

    protected $fillable = [
        'sites', 'on_platform', 'title', 'slug', 'content', 'status', 'locations', 'visibility', 'banner_image',
        'meta_title', 'meta_description', 'meta_keywords', 'canonical_url',
        'sort_order', 'include_in_sitemap', 'published_at',
    ];

    protected $casts = [
        'on_platform' => 'boolean',
        'locations' => 'array',
        'include_in_sitemap' => 'boolean',
        'published_at' => 'datetime',
    ];

    /** @var list<int>|null  domini scelti col modulo, da salvare dopo la pagina */
    private ?array $pendingDomains = null;

    protected static function booted(): void
    {
        static::saved(function (self $page) {
            if ($page->pendingDomains !== null) {
                $page->domains()->sync($page->pendingDomains);
                $page->pendingDomains = null;
                $page->unsetRelation('domains');
            }
        });
    }

    /** I domini della rete su cui la pagina si vede. */
    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class, 'cms_page_domain');
    }

    /**
     * Solo le pagine del sito corrente: quelle scelte per il dominio sul
     * dominio, quelle del sito principale su KSM, nessuna sul dominio di
     * un'azienda (il sito e' la sua pagina).
     */
    public function scopeForSite($query, ?TenantContext $tenant = null)
    {
        $tenant ??= app(TenantContext::class);

        return match (true) {
            $tenant->isCompanySite() => $query->whereRaw('1 = 0'),
            $tenant->isNetworkSite() => $query->whereHas('domains', fn ($q) => $q->whereKey($tenant->domain()->getKey())),
            default => $query->where('on_platform', true),
        };
    }

    /**
     * I siti della pagina come li legge e scrive il modulo: 'platform' per
     * il sito principale, poi gli id dei domini.
     *
     * @return list<string>
     */
    public function getSitesAttribute(): array
    {
        // Una pagina nuova parte dal sito principale, come prima dei domini.
        if (! $this->exists && $this->pendingDomains === null) {
            return [self::PLATFORM];
        }

        return [
            ...($this->on_platform ? [self::PLATFORM] : []),
            ...array_map('strval', $this->pendingDomains ?? $this->domains->modelKeys()),
        ];
    }

    /** @param  list<string|int>|null  $sites */
    public function setSitesAttribute(?array $sites): void
    {
        $sites = array_map('strval', (array) $sites);

        $this->attributes['on_platform'] = in_array(self::PLATFORM, $sites, true);
        $this->pendingDomains = array_values(array_map('intval', array_diff($sites, [self::PLATFORM])));
    }

    /** Per l'elenco in amministrazione. */
    public function getSiteNameAttribute(): string
    {
        return collect([$this->on_platform ? 'Sito principale' : null])
            ->merge($this->domains->pluck('domain'))
            ->filter()
            ->implode(', ') ?: 'Nessun sito';
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
