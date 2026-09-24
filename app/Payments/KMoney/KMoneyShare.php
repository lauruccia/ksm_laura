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
 * Se KMoney ammette solo alcune quote per il conto del venditore, si
 * sceglie solo fra quelle, e la quota finale e' sempre una di quelle.
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

    /**
     * Le quote che il venditore puo' scegliere: quelle ammesse da KMoney
     * per il suo conto (GET /balance), o tutte finche' KMoney non le ha dette.
     *
     * @return array<int, int>
     */
    public static function steps(?CompanyPaymentSetting $settings): array
    {
        $allowed = array_values(array_intersect(self::STEPS, (array) $settings?->kmoney_allowed_percentages));

        return $allowed ?: self::STEPS;
    }

    /**
     * Una quota non piu' ammessa sale alla prima ammessa, o scende all'ultima.
     *
     * Serve alle scelte fatte prima che KMoney restringesse le quote: non
     * si cancellano, valgono come la quota ammessa piu' vicina verso l'alto.
     */
    public static function fit(int $percent, array $steps): int
    {
        foreach ($steps as $step) {
            if ($step >= $percent) {
                return $step;
            }
        }

        return end($steps);
    }

    /** @param  array<int, int>  $categoryRules  categoria => quota, per il venditore del prodotto */
    public static function forProduct(Product $product, ?CompanyPaymentSetting $settings, array $categoryRules): int
    {
        if ($settings?->kmoney_in_debt) {
            return 100;
        }

        if ($product->kmoney_discount_percent !== null) {
            $percent = $product->kmoney_discount_percent;
        } elseif ($product->category_id !== null && array_key_exists($product->category_id, $categoryRules)) {
            $percent = $categoryRules[$product->category_id];
        } else {
            $percent = $settings?->kmoney_contract_percent;
        }

        return self::fit(self::snap($percent), self::steps($settings));
    }
}
