<?php

namespace App\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Chiude un pagamento andato a buon fine.
 *
 * Un ordine puo' avere due pagamenti: la quota KMoney e la parte in euro.
 * L'ordine diventa pagato, e la disponibilita' scende, solo quando sono
 * chiusi tutti e due.
 *
 * E' idempotente: un rientro ricaricato, o rientro e notifica insieme,
 * non scalano la disponibilita' due volte.
 */
class PaymentCompletion
{
    public function markPaid(Order $order, Payment $payment, ?string $reference, array $response): bool
    {
        return DB::transaction(function () use ($order, $payment, $reference, $response) {
            $fresh = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->status === 'completed') {
                return false;
            }

            $fresh->update([
                'status' => 'completed',
                'transaction_id' => $reference ?: $fresh->transaction_id,
                'response' => $response,
            ]);

            $order = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($order && $order->status === 'pending' && $order->isFullyPaid()) {
                $order->update(['status' => 'paid']);

                foreach ($order->items as $item) {
                    if ($item->product_variant_id) {
                        ProductVariant::whereKey($item->product_variant_id)->where('product_id', $item->product_id)
                            ->whereNotNull('variant_stock')->where('variant_stock', '!=', '')
                            ->decrement('variant_stock', $item->quantity);
                    } else {
                        Product::whereKey($item->product_id)->whereNotNull('stock')->decrement('stock', $item->quantity);
                    }
                }
            }

            return true;
        });
    }

    public function markFailed(Payment $payment, array $response): void
    {
        if ($payment->status === 'completed') {
            return;
        }

        $payment->update(['status' => 'failed', 'response' => $response]);
    }
}
