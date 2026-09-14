<?php

namespace App\Payments\KMoney;

use App\Models\Company;
use App\Models\KMoneyCategoryRule;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;

/**
 * Scelte sulle quote KMoney e ricalcolo della quota effettiva.
 *
 * `products.kmoney_percent` e' la quota che risulta oggi da contratto,
 * categorie e scelte sui prodotti. Serve alle schede e al filtro del
 * catalogo, per non rifare il conto su ogni prodotto di ogni pagina. La
 * cassa non si fida di questa copia: ricalcola al momento dell'ordine.
 */
class KMoneyPercentages
{
    /** @return array<int, int> categoria => quota */
    public static function rulesFor(int $companyId): array
    {
        return KMoneyCategoryRule::where('company_id', $companyId)
            ->pluck('percent', 'product_category_id')
            ->map(fn ($percent) => (int) $percent)
            ->all();
    }

    /** Ricalcola la quota effettiva dei prodotti dell'azienda, o solo di quelli indicati. */
    public function refresh(Company $company, ?array $productIds = null): void
    {
        $settings = $company->paymentSettings()->first();
        $rules = self::rulesFor($company->id);
        $groups = [];

        $company->products()
            ->when($productIds !== null, fn ($q) => $q->whereKey($productIds))
            ->select(['id', 'category_id', 'kmoney_discount_percent'])
            ->chunkById(500, function ($products) use (&$groups, $settings, $rules) {
                foreach ($products as $product) {
                    $groups[KMoneyShare::forProduct($product, $settings, $rules)][] = $product->id;
                }
            });

        foreach ($groups as $percent => $ids) {
            foreach (array_chunk($ids, 500) as $chunk) {
                Product::whereKey($chunk)->update(['kmoney_percent' => $percent]);
            }
        }
    }

    /**
     * La stessa quota su piu' prodotti selezionati, come la modifica in blocco di WooCommerce.
     *
     * Tocca solo prodotti dell'azienda indicata. Null riporta i prodotti alla
     * quota automatica, quella di categoria o di contratto.
     */
    public function setProductPercent(Company $company, array $productIds, ?int $percent): int
    {
        $ids = $company->products()->whereKey($productIds)->pluck('id')->all();

        Product::whereKey($ids)->update([
            'kmoney_discount_percent' => $percent === null ? null : KMoneyShare::snap($percent),
        ]);

        $this->refresh($company, $ids);

        return count($ids);
    }

    /** @param  array<int|string, int|string|null>  $rules  categoria => quota; vuoto toglie la scelta */
    public function saveCategoryRules(Company $company, array $rules): void
    {
        $known = ProductCategory::whereKey(array_keys($rules))->pluck('id')->all();

        DB::transaction(function () use ($company, $rules, $known) {
            foreach ($known as $categoryId) {
                $percent = $rules[$categoryId] ?? null;
                $key = ['company_id' => $company->id, 'product_category_id' => $categoryId];

                if ($percent === null || $percent === '') {
                    KMoneyCategoryRule::where($key)->delete();
                } else {
                    KMoneyCategoryRule::updateOrCreate($key, ['percent' => KMoneyShare::snap($percent)]);
                }
            }
        });

        $this->refresh($company);
    }
}
