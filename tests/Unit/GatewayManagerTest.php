<?php

namespace Tests\Unit;

use App\Models\CompanyPaymentSetting;
use App\Payments\GatewayManager;
use App\Payments\PaymentException;
use App\Payments\StripeGateway;
use PHPUnit\Framework\TestCase;

class GatewayManagerTest extends TestCase
{
    private function settings(array $attributes): CompanyPaymentSetting
    {
        return new CompanyPaymentSetting($attributes + ['is_active' => true, 'mode' => 'test']);
    }

    public function test_offre_solo_i_metodi_con_credenziali(): void
    {
        $settings = $this->settings([
            'enable_stripe' => true,
            'stripe_test_secret_key' => 'sk_test_x',
            'enable_paypal' => true,
        ]);

        $this->assertSame(['stripe'], (new GatewayManager())->availableFor($settings));
    }

    public function test_esclude_i_metodi_senza_driver(): void
    {
        $settings = $this->settings([
            'enable_kmoney' => true,
            'kmoney_test_account' => 'DEMO-1',
        ]);

        $this->assertSame([], (new GatewayManager())->availableFor($settings));
    }

    public function test_nessun_metodo_se_le_impostazioni_sono_disattivate(): void
    {
        $settings = $this->settings([
            'is_active' => false,
            'enable_stripe' => true,
            'stripe_test_secret_key' => 'sk_test_x',
        ]);

        $this->assertSame([], (new GatewayManager())->availableFor($settings));
    }

    public function test_risolve_il_driver_richiesto(): void
    {
        $settings = $this->settings(['enable_stripe' => true, 'stripe_test_secret_key' => 'sk_test_x']);

        $this->assertInstanceOf(StripeGateway::class, (new GatewayManager())->driver('stripe', $settings));
    }

    public function test_rifiuta_un_metodo_sconosciuto(): void
    {
        $this->expectException(PaymentException::class);

        (new GatewayManager())->driver('bonifico', $this->settings([]));
    }
}
