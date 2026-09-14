<?php

namespace App\Payments\Subscriptions;

use App\Models\AdminTransaction;
use App\Models\CompanySubscription;
use App\Payments\PaymentException;
use Illuminate\Http\Request;

/**
 * Incassa la quota di un piano per conto della piattaforma.
 *
 * Vale la stessa regola degli ordini: l'esito non si legge dai parametri
 * del rientro, si richiede al gestore.
 */
interface SubscriptionGateway
{
    /** @throws PaymentException */
    public function start(CompanySubscription $subscription, AdminTransaction $transaction): string;

    /** @return array{paid: bool, reference: ?string, response: array} */
    public function confirm(CompanySubscription $subscription, AdminTransaction $transaction, Request $request): array;
}
