<?php

namespace App\Payments\KMoney;

use App\Models\CompanyPaymentSetting;
use App\Models\Product;

/**
 * Quota di un prodotto da pagare in KMoney.
 *
 * Le quote possibili sono cinque: 0, 25, 50, 75, 100. Chi decide, dal
 * piu' forte al piu' debole:
 *
 * 1. il conto KMoney del venditore in debito: 100, senza scelta;
 * 2. la quota scelta sul prodotto, da solo o selezionato con altri;
 * 3. la quota scelta per la categoria del prodotto;
 * 4. la quota del contratto KMoney del venditore.
 *
 * La quota non dipende dall'aver collegato il conto KMoney: e' una scelta
 * del venditore, e non si trasforma in euro da sola. Se il conto manca, e'
 * la cassa a non accettare l'ordine.
 */
final class KMoneyShare
{
    public const STEPS = [0, 25, 50, 75, 100];

    /** Porta un valore qualsiasi alla quota consentita non superiore: 30 diventa 25. */
    public static function snap(int|float|string|null $percent): int
    {
        return intdiv(max(0, min(100, (int) $percent)), 25) * 25;
    }

    /** @param  array<int, int>  $categoryRules  categoria => quota, per il venditore del prodotto */
    public static function forProduct(Product $product, ?CompanyPaymentSetting $settings, array $categoryRules): int
    {
        if ($settings?->kmoney_in_debt) {
            return 100;
        }

        if ($product->kmoney_discount_percent !== null) {
            return self::snap($product->kmoney_discount_percent);
        }

        if ($product->category_id !== null && array_key_exists($product->category_id, $categoryRules)) {
            return self::snap($categoryRules[$product->category_id]);
        }

        return self::snap($settings?->kmoney_contract_percent);
    }
}
