<?php

namespace App\Models;

use App\Support\PlanCapabilities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use Concerns\HasDomainConnection;

    /** Colonna con il nome del dominio, per lo stato di collegamento. */
    public function domainColumn(): string
    {
        return 'custom_domain';
    }

    protected $fillable = [
        'user_id', 'plan_id', 'category_id', 'name', 'slug', 'custom_domain', 'force_www',
        'address', 'city', 'region', 'website', 'phone', 'email', 'banner', 'logo',
        'working_hours', 'offer_gallery', 'company_description', 'company_location',
        'personal_page', 'is_active', 'base_shipping_rate', 'per_kg_rate',
        'latitude', 'longitude',
    ];

    protected $casts = [
        'working_hours' => 'array',
        'offer_gallery' => 'array',
        'is_active' => 'boolean',
        'force_www' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CompanyCategory::class, 'category_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function paymentSettings(): HasOne
    {
        return $this->hasOne(CompanyPaymentSetting::class);
    }

    /** Profilo nel circuito banner, se l'azienda compra campagne. */
    public function advertiser(): HasOne
    {
        return $this->hasOne(Advertiser::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanySubscription::class);
    }

    /** L'abbonamento in corso, se ce n'e' uno pagato e non scaduto. */
    public function activeSubscription(): ?CompanySubscription
    {
        return $this->subscriptions()->active()->with('plan')->latest('ends_at')->first();
    }

    /** L'ultimo abbonamento in attesa di incasso, tipico del bonifico. */
    public function pendingSubscription(): ?CompanySubscription
    {
        return $this->subscriptions()
            ->where('status', CompanySubscription::PENDING)
            ->with('plan')
            ->latest()
            ->first();
    }

    /**
     * L'azienda puo' fare questa cosa?
     *
     * `plan_id` resta allineato all'abbonamento in corso: quando scade
     * viene azzerato, e l'azienda non concede piu' nulla.
     */
    public function allows(string $capability): bool
    {
        return (bool) $this->plan?->allows($capability);
    }

    public function hasPlan(): bool
    {
        return $this->plan_id !== null;
    }

    /**
     * Ha una pagina pubblica tutta sua? La concede la vetrina completa
     * (Ecommerce e Vetrina); biglietto e anagrafica restano solo una
     * scheda nella directory, senza link e senza banner.
     */
    public function hasPage(): bool
    {
        return $this->allows(PlanCapabilities::SHOWCASE);
    }

    public function scopeActive($query)
    {
        // Qualificata: la directory unisce `plans`, che ha la stessa colonna.
        return $query->where('companies.is_active', true);
    }

    /**
     * Solo le aziende il cui piano concede la presenza nella directory.
     *
     * `plan_id in (piani che la concedono)` invece di whereHas: la
     * sottoquery sui piani gira una volta sola, non per ogni azienda.
     */
    public function scopeInDirectory($query)
    {
        return $query->whereIn(
            'companies.plan_id',
            Plan::query()->whereJsonContains('capabilities', PlanCapabilities::DIRECTORY)->select('plans.id')
        );
    }

    /** Solo le aziende con una pagina pubblica: serve alla sitemap. */
    public function scopeWithPage($query)
    {
        return $query->whereIn(
            'companies.plan_id',
            Plan::query()->whereJsonContains('capabilities', PlanCapabilities::SHOWCASE)->select('plans.id')
        );
    }

    /** Solo le aziende che possono vendere: serve allo shop pubblico. */
    public function scopeSelling($query)
    {
        // Come InDirectory: i piani sono pochi, meglio la lista degli id che
        // una sottoquery ripetuta per ogni azienda (e per ogni prodotto nello shop).
        return $query->whereIn(
            'companies.plan_id',
            Plan::query()->whereJsonContains('capabilities', PlanCapabilities::SHOP)->select('plans.id')
        );
    }
}
