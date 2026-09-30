<?php

namespace App\Support\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * La disponibilita' dei prodotti impegnata da un ordine.
 *
 * Regola unica: un ordine pagato, spedito o concluso ha scalato la merce,
 * uno in attesa o annullato no. sync() porta la disponibilita' a
 * rispettarla dopo qualsiasi cambio (stato, righe), e
 * `orders.stock_deducted_at` impedisce di scalare o restituire due volte.
 *
 * I prodotti senza gestione della disponibilita' (giacenza vuota) non si
 * toccano, come al pagamento.
 */
class OrderStock
{
    public function sync(Order $order): void
    {
        $due = in_array($order->status, Order::STOCK_STATUSES, true);

        if ($due && ! $order->stock_deducted_at) {
            $this->deduct($order);
        } elseif (! $due && $order->stock_deducted_at) {
            $this->restore($order);
        }
    }

    public function deduct(Order $order): void
    {
        if ($order->stock_deducted_at) {
            return;
        }

        foreach ($order->items()->get() as $item) {
            $this->move($item, -$item->quantity);
        }

        $order->forceFill(['stock_deducted_at' => now()])->save();
    }

    public function restore(Order $order): void
    {
        if (! $order->stock_deducted_at) {
            return;
        }

        foreach ($order->items()->get() as $item) {
            $this->move($item, $item->quantity);
        }

        $order->forceFill(['stock_deducted_at' => null])->save();
    }

    private function move(OrderItem $item, int $quantity): void
    {
        if ($quantity === 0) {
            return;
        }

        if ($item->product_variant_id) {
            $variant = ProductVariant::whereKey($item->product_variant_id)->where('product_id', $item->product_id)
                ->whereNotNull('variant_stock')->where('variant_stock', '!=', '')->lockForUpdate()->first();

            if ($variant) {
                $variant->variant_stock = (string) ((int) $variant->variant_stock + $quantity);
                $variant->save();
            }

            return;
        }

        $product = Product::whereKey($item->product_id)->whereNotNull('stock')->lockForUpdate()->first();

        if ($product) {
            $product->stock += $quantity;
            $product->save();
        }
    }
}
