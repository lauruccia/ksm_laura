<?php

namespace Tests\Unit;

use App\Models\AdminPaymentSetting;
use App\Payments\PaymentException;
use App\Payments\Subscriptions\PayPalSubscriptionGateway;
use App\Payments\Subscriptions\SubscriptionGatewayManager;
use PHPUnit\Framework\TestCase;

class SubscriptionGatewayManagerTest extends TestCase
{
    private function settings(array $attributes): AdminPaymentSetting
    {
        return new AdminPaymentSetting($attributes + ['is_active' => true, 'mode' => 'test']);
    }

    public function test_offre_solo_i_metodi_con_credenziali(): void
    {
        $settings = $this->settings([
            'enable_paypal' => true,
            'paypal_test_client_id' => 'client',
            'paypal_test_secret' => 'segreto',
            'enable_stripe' => true,
        ]);

        $this->assertSame(['paypal'], (new SubscriptionGatewayManager())->availableFor($settings));
    }

    public function test_il_bonifico_serve_l_iban(): void
    {
        $manager = new SubscriptionGatewayManager();

        $senzaIban = $this->settings(['enable_bank_transfer' => true]);
        $conIban = $this->settings([
            'enable_bank_transfer' => true,
            'bank_iban' => 'IT60X0542811101000000123456',
        ]);

        $this->assertSame([], $manager->availableFor($senzaIban));
        $this->assertSame(['bank_transfer'], $manager->availableFor($conIban));
    }

    public function test_nessun_metodo_se_i_pagamenti_sono_disattivati(): void
    {
        $settings = $this->settings([
            'is_active' => false,
            'enable_bank_transfer' => true,
            'bank_iban' => 'IT60X0542811101000000123456',
        ]);

        $this->assertSame([], (new SubscriptionGatewayManager())->availableFor($settings));
    }

    public function test_risolve_il_driver_richiesto(): void
    {
        $settings = $this->settings(['enable_paypal' => true, 'paypal_test_secret' => 'segreto']);

        $this->assertInstanceOf(
            PayPalSubscriptionGateway::class,
            (new SubscriptionGatewayManager())->driver('paypal', $settings)
        );
    }

    public function test_il_bonifico_non_ha_un_driver(): void
    {
        $this->expectException(PaymentException::class);

        (new SubscriptionGatewayManager())->driver('bank_transfer', $this->settings([]));
    }
}
