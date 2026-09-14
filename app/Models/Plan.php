<?php

namespace App\Models;

use App\Support\PlanCapabilities;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'price', 'duration_days',
        'is_active', 'priority', 'features', 'capabilities',
    ];

    protected $casts = [
        'features' => 'array',
        'capabilities' => 'array',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanySubscription::class);
    }

    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    /** Senza durata il piano, una volta attivo, non scade. */
    public function isLifetime(): bool
    {
        return $this->duration_days === null;
    }

    /** Fine di un periodo che parte da questa data; null se il piano non scade. */
    public function endsFrom(CarbonInterface $start): ?CarbonInterface
    {
        return $this->isLifetime() ? null : $start->copy()->addDays((int) $this->duration_days);
    }

    /** Il piano concede questa voce? */
    public function allows(string $capability): bool
    {
        return in_array($capability, (array) $this->capabilities, true);
    }

    /** Etichette delle voci concesse, per mostrarle senza ripeterle a mano. */
    public function capabilityLabels(): array
    {
        return array_map(
            fn ($key) => PlanCapabilities::label($key),
            PlanCapabilities::sanitize((array) $this->capabilities)
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('plans.is_active', true);
    }

    /**
     * Dal piu' ricco al piu' economico.
     *
     * Comanda `priority`, che l'amministratore imposta a mano; a parita'
     * decide il prezzo. E' lo stesso ordine usato nella directory.
     */
    public function scopeByRank(Builder $query): Builder
    {
        return $query->orderByDesc('priority')->orderByDesc('price')->orderBy('id');
    }
}
