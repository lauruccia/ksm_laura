<?php

namespace App\Payments\Subscriptions;

use App\Models\CompanySubscription;
use App\Models\Plan;
use Carbon\CarbonInterface;

/**
 * Quanto costa passare a un piano, e fino a quando vale.
 *
 * E' un preventivo: non tocca niente, si limita a dire cifra e scadenza.
 * Chi lo usa lo mostra all'azienda prima di farle aprire il pagamento.
 */
final class SubscriptionQuote
{
    public const NEW = 'new';

    public const RENEWAL = 'renewal';

    public const CHANGE = 'change';

    public function __construct(
        public readonly Plan $plan,
        public readonly string $kind,
        public readonly float $amount,
        public readonly float $credit,
        public readonly CarbonInterface $startsAt,
        /** Null se il piano scelto non scade. */
        public readonly ?CarbonInterface $endsAt,
        public readonly ?CompanySubscription $replaces = null,
        public readonly int $remainingDays = 0,
    ) {
    }

    /** Non c'e' niente da incassare: il piano parte subito. */
    public function isFree(): bool
    {
        return $this->amount <= 0;
    }

    public function isChange(): bool
    {
        return $this->kind === self::CHANGE;
    }

    /** Spiegazione della cifra, da mostrare accanto al pulsante. */
    public function explain(): string
    {
        return match (true) {
            $this->kind === self::RENEWAL && ! $this->replaces?->ends_at => 'Il piano non scade: non c\'e niente da rinnovare.',
            $this->kind === self::RENEWAL => 'Rinnovo: il periodo riparte dalla scadenza attuale.',
            $this->kind === self::CHANGE && ! $this->replaces?->ends_at => 'Cambio da un piano senza scadenza: la quota gia pagata vale come sconto.',
            $this->kind === self::CHANGE && ! $this->endsAt => 'Il piano nuovo non scade: paghi la quota intera, scontato il residuo del piano attuale.',
            $this->kind === self::CHANGE && $this->credit > 0 => sprintf(
                'Cambio a meta periodo: paghi la differenza per i %d giorni che restano, scontato il residuo del piano attuale. La scadenza non cambia.',
                $this->remainingDays
            ),
            $this->kind === self::CHANGE => 'Cambio di piano: la scadenza non cambia.',
            ! $this->endsAt => 'Il piano parte oggi e non scade.',
            default => 'Primo periodo: parte oggi.',
        };
    }
}
