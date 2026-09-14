<?php

namespace Tests\Feature;

use App\Models\CompanyPaymentSetting;
use App\Payments\PaymentException;
use App\Payments\StripeGateway;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Verifica la firma delle notifiche usando il driver vero.
 *
 * La firma viene calcolata qui con lo stesso schema di Stripe, quindi
 * il controllo esercitato e' quello che gira in produzione.
 */
class StripeSignatureTest extends TestCase
{
    private const SECRET = 'whsec_segreto_di_prova';

    private function gateway(?string $secret = self::SECRET): StripeGateway
    {
        return new StripeGateway(new CompanyPaymentSetting([
            'mode' => 'test',
            'stripe_webhook_secret' => $secret,
        ]));
    }

    private function request(array $event, ?string $secret = self::SECRET, int $age = 0): Request
    {
        $payload = json_encode($event);
        $timestamp = time() - $age;

        $signature = $secret === null
            ? 'firma-inventata'
            : 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return Request::create(
            '/webhook/stripe/1', 'POST', [], [], [],
            ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );
    }

    private function event(string $type, string $sessionId = 'cs_test_123'): array
    {
        return [
            'id' => 'evt_1',
            'type' => $type,
            'data' => ['object' => ['id' => $sessionId, 'object' => 'checkout.session']],
        ];
    }

    public function test_accetta_una_notifica_firmata_correttamente(): void
    {
        $reference = $this->gateway()->webhookReference(
            $this->request($this->event('checkout.session.completed'))
        );

        $this->assertSame('cs_test_123', $reference);
    }

    public function test_accetta_anche_l_incasso_differito(): void
    {
        $reference = $this->gateway()->webhookReference(
            $this->request($this->event('checkout.session.async_payment_succeeded'))
        );

        $this->assertSame('cs_test_123', $reference);
    }

    public function test_ignora_gli_eventi_che_non_riguardano_incassi(): void
    {
        $this->assertNull($this->gateway()->webhookReference(
            $this->request($this->event('customer.created'))
        ));
    }

    public function test_rifiuta_una_firma_calcolata_con_un_altro_segreto(): void
    {
        $this->expectException(PaymentException::class);

        $this->gateway()->webhookReference(
            $this->request($this->event('checkout.session.completed'), secret: 'whsec_segreto_sbagliato')
        );
    }

    public function test_rifiuta_una_firma_inventata(): void
    {
        $this->expectException(PaymentException::class);

        $this->gateway()->webhookReference(
            $this->request($this->event('checkout.session.completed'), secret: null)
        );
    }

    public function test_rifiuta_una_notifica_troppo_vecchia(): void
    {
        $this->expectException(PaymentException::class);

        // Oltre la tolleranza di Stripe: difende dal rinvio di notifiche catturate.
        $this->gateway()->webhookReference(
            $this->request($this->event('checkout.session.completed'), age: 3600)
        );
    }

    public function test_rifiuta_se_il_segreto_non_e_configurato(): void
    {
        $this->expectException(PaymentException::class);

        $this->gateway(secret: null)->webhookReference(
            $this->request($this->event('checkout.session.completed'))
        );
    }
}
