<?php

namespace App\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Apre una connessione autenticata verso PayPal.
 *
 * Sta qui perche' la usano due strade diverse: l'ordine, che incassa
 * l'azienda, e l'abbonamento, che incassa la piattaforma.
 */
final class PayPalHttp
{
    private const LIVE = 'https://api-m.paypal.com';

    private const SANDBOX = 'https://api-m.sandbox.paypal.com';

    /**
     * @param  array{client_id: ?string, secret: ?string}  $keys
     *
     * @throws PaymentException se mancano le credenziali o il token
     */
    public static function client(array $keys, bool $live, string $owner): PendingRequest
    {
        if (blank($keys['client_id']) || blank($keys['secret'])) {
            throw new PaymentException("Credenziali PayPal non configurate per $owner.");
        }

        $base = $live ? self::LIVE : self::SANDBOX;

        $token = Http::asForm()
            ->withBasicAuth($keys['client_id'], $keys['secret'])
            ->post("$base/v1/oauth2/token", ['grant_type' => 'client_credentials'])
            ->json('access_token');

        if (blank($token)) {
            throw new PaymentException('PayPal non ha rilasciato il token di accesso.');
        }

        return Http::baseUrl($base)->withToken($token)->acceptJson()->timeout(20);
    }

    /** Il collegamento a cui mandare chi paga. */
    public static function approvalLink(array $links): string
    {
        foreach (['payer-action', 'approve'] as $rel) {
            $link = collect($links)->firstWhere('rel', $rel);

            if ($link) {
                return $link['href'];
            }
        }

        throw new PaymentException('PayPal non ha restituito il collegamento di approvazione.');
    }
}
