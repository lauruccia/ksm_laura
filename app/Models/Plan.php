<?php

namespace App\Models;

use App\Support\PlanCapabilities;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    /**
     * Voci da mostrare sulla scheda del piano.
     *
     * Quelle scritte a mano vincono sulle etichette delle capacita'. Le voci
     * tutte maiuscole arrivano cosi' dall'amministrazione: si leggono in minuscolo.
     *
     * @return array<int, string>
     */
    public function featureItems(): array
    {
        return collect($this->features ?: $this->capabilityLabels())
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->map(fn ($item) => mb_strtoupper($item) === $item ? Str::ucfirst(mb_strtolower($item)) : $item)
            ->values()
            ->all();
    }

    /**
     * Tutte le voci dei piani messi a confronto, senza doppioni.
     *
     * Parte dal piano con piu' voci, cosi' l'ordine resta il suo; quelle che
     * compaiono solo negli altri piani finiscono in coda.
     *
     * @param  iterable<Plan>  $plans
     * @return array<string, string> chiave di confronto => voce
     */
    public static function featureUnion(iterable $plans): array
    {
        $union = [];

        foreach (collect($plans)->sortByDesc(fn (Plan $plan) => count($plan->featureItems())) as $plan) {
            foreach ($plan->featureItems() as $item) {
                $union[self::featureKey($item)] ??= $item;
            }
        }

        return $union;
    }

    /** "E-Mail", "e-mail " ed "E-mail" sono la stessa voce. */
    public static function featureKey(string $item): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($item)));
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
