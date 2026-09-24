<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPaymentSetting extends Model
{
    use Concerns\HasGatewayCredentials;

    /*
     * Quota del contratto e debito KMoney non sono fra i campi assegnabili:
     * li imposta l'amministrazione, mai il modulo del venditore.
     */
    protected $fillable = [
        'company_id', 'mode', 'enable_stripe', 'enable_paypal', 'enable_kmoney',
        'stripe_test_public_key', 'stripe_test_secret_key',
        'stripe_live_public_key', 'stripe_live_secret_key',
        'paypal_test_client_id', 'paypal_test_secret',
        'paypal_live_client_id', 'paypal_live_secret',
        'kmoney_api_token', 'kmoney_webhook_secret',
        'stripe_webhook_secret', 'paypal_webhook_id', 'is_active',
    ];

    protected $casts = [
        'enable_stripe' => 'boolean',
        'enable_paypal' => 'boolean',
        'enable_kmoney' => 'boolean',
        'is_active' => 'boolean',
        'kmoney_api_token' => 'encrypted',
        'kmoney_webhook_secret' => 'encrypted',
        'kmoney_contract_percent' => 'integer',
        'kmoney_in_debt' => 'boolean',
        'kmoney_can_sell' => 'boolean',
        'kmoney_allowed_percentages' => 'array',
        'kmoney_synced_at' => 'datetime',
        'kmoney_pairing_secret' => 'encrypted',
        'kmoney_pairing_requested_at' => 'datetime',
        'kmoney_pairing_checked_at' => 'datetime',
    ];

    protected $hidden = [
        'stripe_test_secret_key', 'stripe_live_secret_key',
        'paypal_test_secret', 'paypal_live_secret', 'stripe_webhook_secret',
        'kmoney_api_token', 'kmoney_webhook_secret', 'kmoney_pairing_secret',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** La quota KMoney si puo' incassare: KMoney attivato e token del venditore presente. */
    public function kmoneyReady(): bool
    {
        return (bool) $this->enable_kmoney && filled($this->kmoney_api_token);
    }
}
