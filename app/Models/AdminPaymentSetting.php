<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminPaymentSetting extends Model
{
    use Concerns\HasGatewayCredentials;

    protected $fillable = [
        'mode', 'enable_stripe', 'enable_paypal', 'enable_kmoney', 'enable_bank_transfer',
        'stripe_test_public_key', 'stripe_test_secret_key',
        'stripe_live_public_key', 'stripe_live_secret_key',
        'paypal_test_client_id', 'paypal_test_secret',
        'paypal_live_client_id', 'paypal_live_secret',
        'kmoney_test_account', 'kmoney_live_account',
        'stripe_webhook_secret', 'paypal_webhook_id', 'is_active',
        'bank_holder', 'bank_iban', 'bank_bic', 'bank_instructions', 'kmoney_euro_fallback',
    ];

    protected $casts = [
        'kmoney_euro_fallback' => 'boolean',
        'enable_stripe' => 'boolean',
        'enable_paypal' => 'boolean',
        'enable_kmoney' => 'boolean',
        'enable_bank_transfer' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'stripe_test_secret_key', 'stripe_live_secret_key',
        'paypal_test_secret', 'paypal_live_secret', 'stripe_webhook_secret',
    ];

    /**
     * Riga unica: le credenziali della piattaforma, quelle con cui si
     * incassano gli abbonamenti ai piani.
     */
    public static function current(): self
    {
        return static::firstOrCreate([]);
    }

    /** Il bonifico non ha credenziali da verificare: basta l'IBAN da mostrare. */
    public function availableMethods(): array
    {
        $methods = $this->gatewayMethods();

        if ($this->enable_bank_transfer && filled($this->bank_iban)) {
            $methods[] = 'bank_transfer';
        }

        return $methods;
    }
}
