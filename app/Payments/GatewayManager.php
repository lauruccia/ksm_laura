<?php

namespace App\Payments;

use App\Models\CompanyPaymentSetting;

/**
 * Sceglie il gateway giusto per l'azienda che vende.
 *
 * Un metodo si puo' offrire solo se l'azienda lo ha attivato, ha le
 * credenziali, e qui esiste un driver che lo sa gestire.
 *
 * KMoney non e' un metodo fra gli altri: e' la quota di un ordine che si
 * paga in KY, prima della parte in euro. Per questo non compare nei metodi
 * in euro e ha una domanda sua.
 */
class GatewayManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const DRIVERS = [
        'stripe' => StripeGateway::class,
        'paypal' => PayPalGateway::class,
        'kmoney' => KMoneyGateway::class,
    ];

    /** Metodi con cui si paga la parte in euro. */
    public const EURO_METHODS = ['stripe', 'paypal'];

    /** Metodi che questo strato sa portare a termine. */
    public function supported(): array
    {
        return array_keys(self::DRIVERS);
    }

    /** Metodi in euro realmente proponibili all'acquirente di questa azienda. */
    public function availableFor(?CompanyPaymentSetting $settings): array
    {
        if (! $settings || ! $settings->is_active) {
            return [];
        }

        return array_values(array_intersect($settings->availableMethods(), self::EURO_METHODS));
    }

    /** La quota KMoney di questa azienda si puo' incassare? */
    public function kmoneyAvailable(?CompanyPaymentSetting $settings): bool
    {
        return (bool) ($settings?->is_active && $settings->kmoneyReady());
    }

    public function driver(string $method, CompanyPaymentSetting $settings): PaymentGateway
    {
        $class = self::DRIVERS[$method] ?? null;

        if (! $class) {
            throw new PaymentException("Metodo di pagamento non gestito: $method");
        }

        return new $class($settings);
    }

    /** Etichetta mostrata all'acquirente. */
    public static function label(string $method): string
    {
        return match ($method) {
            'stripe' => 'Carta di credito',
            'paypal' => 'PayPal',
            'kmoney' => 'KMoney',
            default => ucfirst($method),
        };
    }
}
