<?php

namespace App\Payments\Subscriptions;

use App\Models\AdminPaymentSetting;
use App\Payments\PaymentException;

/**
 * Sceglie come far pagare la quota del piano.
 *
 * Un metodo si puo' offrire solo se l'amministratore lo ha attivato,
 * ha messo le credenziali, e qui esiste qualcosa che lo sa gestire.
 * Il bonifico fa eccezione: non ha un gestore, ha un IBAN.
 */
class SubscriptionGatewayManager
{
    public const BANK_TRANSFER = 'bank_transfer';

    /** @var array<string, class-string<SubscriptionGateway>> */
    private const DRIVERS = [
        'stripe' => StripeSubscriptionGateway::class,
        'paypal' => PayPalSubscriptionGateway::class,
    ];

    /** Metodi che questo strato sa portare a termine da solo. */
    public function supported(): array
    {
        return array_merge(array_keys(self::DRIVERS), [self::BANK_TRANSFER]);
    }

    /** Metodi realmente proponibili all'azienda che si abbona. */
    public function availableFor(?AdminPaymentSetting $settings): array
    {
        if (! $settings || ! $settings->is_active) {
            return [];
        }

        return array_values(array_intersect($settings->availableMethods(), $this->supported()));
    }

    public function driver(string $method, AdminPaymentSetting $settings): SubscriptionGateway
    {
        $class = self::DRIVERS[$method] ?? null;

        if (! $class) {
            throw new PaymentException("Metodo di pagamento non gestito: $method");
        }

        return new $class($settings);
    }

    /** Il bonifico non porta fuori dal sito: lo conferma l'amministratore. */
    public function isBankTransfer(string $method): bool
    {
        return $method === self::BANK_TRANSFER;
    }

    /** Etichetta mostrata a chi si abbona. */
    public static function label(string $method): string
    {
        return match ($method) {
            'stripe' => 'Carta di credito',
            'paypal' => 'PayPal',
            self::BANK_TRANSFER => 'Bonifico bancario',
            default => ucfirst($method),
        };
    }
}
