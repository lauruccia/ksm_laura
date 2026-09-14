<?php

namespace App\Models\Concerns;

/**
 * Legge le credenziali del gateway attivo senza esporle nelle viste.
 * Restituisce sempre la coppia relativa alla modalita' corrente, test o live.
 */
trait HasGatewayCredentials
{
    public function isLive(): bool
    {
        return $this->mode === 'live';
    }

    public function stripeKeys(): array
    {
        return $this->isLive()
            ? ['public' => $this->stripe_live_public_key, 'secret' => $this->stripe_live_secret_key]
            : ['public' => $this->stripe_test_public_key, 'secret' => $this->stripe_test_secret_key];
    }

    public function paypalKeys(): array
    {
        return $this->isLive()
            ? ['client_id' => $this->paypal_live_client_id, 'secret' => $this->paypal_live_secret]
            : ['client_id' => $this->paypal_test_client_id, 'secret' => $this->paypal_test_secret];
    }

    public function kmoneyAccount(): ?string
    {
        return $this->isLive() ? $this->kmoney_live_account : $this->kmoney_test_account;
    }

    /** Metodi realmente utilizzabili in questo momento. */
    public function availableMethods(): array
    {
        return $this->gatewayMethods();
    }

    /** I soli metodi che passano da un gestore esterno. */
    protected function gatewayMethods(): array
    {
        $methods = [];

        if ($this->enable_stripe && filled($this->stripeKeys()['secret'])) {
            $methods[] = 'stripe';
        }

        if ($this->enable_paypal && filled($this->paypalKeys()['secret'])) {
            $methods[] = 'paypal';
        }

        if ($this->enable_kmoney && filled($this->kmoneyAccount())) {
            $methods[] = 'kmoney';
        }

        return $methods;
    }
}
