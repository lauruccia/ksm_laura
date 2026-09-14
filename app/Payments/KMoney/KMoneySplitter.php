<?php

namespace App\Payments\KMoney;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Divide il carrello fra KMoney ed euro.
 *
 * Ogni riga paga in KMoney la quota del suo prodotto, arrotondata per
 * difetto al centesimo: il resto va in euro. La spedizione segue la quota
 * piu' bassa del carrello, cosi' nessuno paga in KMoney piu' di quanto il
 * venditore abbia accettato sul prodotto meno KMoney.
 *
 * Con $allEuro l'acquirente senza conto KMoney paga tutto in euro. Se gli
 * sia permesso lo decide la cassa, non questa classe.
 */
class KMoneySplitter
{
    /** @param  Collection<int|string, array{product_id: int, price: float|string, quantity: int}>  $items */
    public function split(Company $company, Collection $items, float $shipping, bool $allEuro = false): CheckoutSplit
    {
        $settings = $company->paymentSettings;
        $rules = KMoneyPercentages::rulesFor($company->id);

        $products = Product::whereKey($items->pluck('product_id'))
            ->get(['id', 'category_id', 'kmoney_discount_percent'])
            ->keyBy('id');

        $lines = [];
        $subtotalCents = 0;
        $kmoneyCents = 0;

        foreach ($items as $item) {
            $lineCents = self::cents((float) $item['price'] * (int) $item['quantity']);
            $product = $products->get($item['product_id']);

            $percent = $allEuro || ! $product ? 0 : KMoneyShare::forProduct($product, $settings, $rules);
            $kmoney = intdiv($lineCents * $percent, 100);

            $lines[(int) $item['product_id']] = ['percent' => $percent, 'kmoney' => $kmoney];
            $subtotalCents += $lineCents;
            $kmoneyCents += $kmoney;
        }

        $shippingPercent = $lines ? min(array_column($lines, 'percent')) : 0;
        $shippingCents = self::cents($shipping);
        $kmoneyCents += intdiv($shippingCents * $shippingPercent, 100);

        return new CheckoutSplit(
            lines: $lines,
            shippingPercent: $shippingPercent,
            totalCents: $subtotalCents + $shippingCents,
            kmoneyCents: $kmoneyCents,
            vendorInDebt: (bool) $settings?->kmoney_in_debt,
        );
    }

    public static function cents(float|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
