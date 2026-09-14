<?php

namespace App\Models;

use App\Support\Ads\AdContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Una campagna del circuito banner.
 *
 * E' in corso se accesa, dentro le date e sotto i limiti acquistati. Il
 * modo di pagare dice quale limite conta: le date per il periodo, le
 * visualizzazioni o i clic per gli altri due. Raggiunto il limite, la
 * campagna smette di comparire da sola.
 */
class Advertisement extends Model
{
    public const BILLING = [
        'period' => 'A periodo',
        'impressions' => 'A visualizzazioni',
        'clicks' => 'A clic',
    ];

    /* Visualizzazioni e clic non si assegnano: li conta AdServer. */
    protected $fillable = [
        'advertiser_id', 'name', 'link', 'img', 'locations', 'status', 'billing',
        'starts_at', 'ends_at', 'max_impressions', 'max_clicks',
        'target_domains', 'target_cities', 'target_categories',
    ];

    protected $casts = [
        'locations' => 'array',
        'target_domains' => 'array',
        'target_cities' => 'array',
        'target_categories' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'status' => 'integer',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'max_impressions' => 'integer',
        'max_clicks' => 'integer',
    ];

    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(Advertiser::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(AdvertisementStat::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopeInLocation(Builder $query, string $location): Builder
    {
        return $query->whereJsonContains('locations', $location);
    }

    /** In corso: accesa, dentro le date, sotto i limiti, con l'inserzionista attivo. */
    public function scopeRunning(Builder $query): Builder
    {
        $now = now();

        return $query->where('advertisements.status', 1)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now->copy()->startOfDay()))
            ->where(fn ($q) => $q->whereNull('max_impressions')->orWhereColumn('impressions', '<', 'max_impressions'))
            ->where(fn ($q) => $q->whereNull('max_clicks')->orWhereColumn('clicks', '<', 'max_clicks'))
            ->where(fn ($q) => $q->whereNull('advertiser_id')
                ->orWhereHas('advertiser', fn ($advertiser) => $advertiser->where('is_active', true)));
    }

    /** La campagna e' mirata su questo contesto? Un bersaglio vuoto vale ovunque. */
    public function matches(AdContext $context): bool
    {
        $domains = array_map('intval', (array) $this->target_domains);

        if ($domains && ! in_array($context->domainId, $domains, true)) {
            return false;
        }

        $cities = array_map(fn ($city) => mb_strtolower(trim((string) $city)), (array) $this->target_cities);

        if ($cities && ! in_array(mb_strtolower(trim((string) $context->city)), $cities, true)) {
            return false;
        }

        $categories = array_map('intval', (array) $this->target_categories);

        return ! $categories || array_intersect($categories, $context->categoryIds) !== [];
    }

    public function stateLabel(): string
    {
        return match (true) {
            ! $this->status => 'Spenta',
            $this->advertiser && ! $this->advertiser->is_active => 'Inserzionista sospeso',
            $this->starts_at?->isFuture() => 'Programmata',
            $this->ends_at && $this->ends_at->copy()->endOfDay()->isPast() => 'Scaduta',
            $this->max_impressions !== null && $this->impressions >= $this->max_impressions => 'Visualizzazioni esaurite',
            $this->max_clicks !== null && $this->clicks >= $this->max_clicks => 'Clic esauriti',
            default => 'In corso',
        };
    }

    public function isRunning(): bool
    {
        return $this->stateLabel() === 'In corso';
    }

    /** Percentuale di clic sulle visualizzazioni. */
    public function ctr(): float
    {
        return $this->impressions > 0 ? round($this->clicks / $this->impressions * 100, 2) : 0.0;
    }

    /**
     * Indirizzo a cui la pagina segnala che il banner e' stato visto.
     *
     * Firmato e con scadenza: una visualizzazione si conta solo da una
     * pagina che il sito ha davvero servito, e una volta sola.
     */
    public function viewUrl(): string
    {
        return URL::temporarySignedRoute(
            'ads.view',
            now()->addHours(6),
            ['advertisement' => $this->id, 'n' => Str::random(16)],
            absolute: false
        );
    }

    /** @return Collection<int, AdvertisementStat> */
    public function recentStats(int $days = 60): Collection
    {
        return $this->stats()
            ->where('day', '>=', now()->subDays($days - 1)->toDateString())
            ->orderByDesc('day')
            ->get();
    }
}
