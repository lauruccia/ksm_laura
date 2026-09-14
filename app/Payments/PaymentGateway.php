<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Un gateway porta l'acquirente fuori dal sito e lo riporta indietro.
 *
 * L'esito non si legge mai dai parametri del rientro: si richiede al
 * gateway, che e' l'unica fonte attendibile.
 */
interface PaymentGateway
{
    /**
     * Prepara il pagamento e restituisce l'indirizzo a cui mandare l'acquirente.
     *
     * @throws PaymentException se il gateway non risponde o rifiuta la richiesta
     */
    public function start(Order $order, Payment $payment): string;

    /**
     * Verifica l'esito al rientro. Restituisce true solo a incasso confermato.
     *
     * @return array{paid: bool, reference: ?string, response: array}
     */
    public function confirm(Order $order, Payment $payment, Request $request): array;
}
