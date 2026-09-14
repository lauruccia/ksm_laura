<?php

namespace App\Support\Legacy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dati inventati per provare il vecchio sito.
 *
 * Aziende degli sviluppatori, prodotti chiamati "test" e marche di
 * fantasia: sul sito vero non devono comparire. L'importazione li toglie
 * dopo aver scritto le tabelle; su un database gia' importato si lancia
 * `php artisan legacy:purge-test-data`.
 *
 * Le righe collegate si cancellano una per una invece di contare sulle
 * cascate: durante l'importazione i vincoli tra tabelle sono spenti.
 */
final class LegacyTestData
{
    /**
     * Aziende di prova, per id: l'importazione tiene quelli originali.
     * Niente email qui, il codice e' pubblico.
     */
    public const COMPANY_IDS = [
        109844, // Testing Laura, email usa e getta
        110590, // Cyphersol
        110602, // ALex
    ];

    /** Prodotti di prova dentro aziende vere, per nome esatto. */
    public const PRODUCT_NAMES = ['test'];

    /** Marche di fantasia, per slug. */
    public const BRAND_SLUGS = ['yoshi-griffith'];

    /** @return array{companies: int, users: int, products: int, brands: int} righe tolte */
    public function purge(): array
    {
        $companies = DB::table('companies')->whereIn('id', self::COMPANY_IDS)->get(['id', 'user_id']);
        $companyIds = $companies->pluck('id')->all();
        $userIds = $companies->pluck('user_id')->filter()->all();

        $productIds = DB::table('products')
            ->whereIn('company_id', $companyIds)
            ->orWhereIn(DB::raw('lower(name)'), self::PRODUCT_NAMES)
            ->pluck('id')
            ->all();

        $orderIds = DB::table('orders')
            ->whereIn('company_id', $companyIds)
            ->orWhereIn('user_id', $userIds)
            ->pluck('id')
            ->all();

        $this->delete('order_items', 'order_id', $orderIds);
        $this->delete('order_items', 'product_id', $productIds);
        $this->delete('product_reviews', 'product_id', $productIds);
        $this->delete('product_reviews', 'user_id', $userIds);
        $this->delete('product_variants', 'product_id', $productIds);
        $products = $this->delete('products', 'id', $productIds);
        $this->delete('orders', 'id', $orderIds);

        foreach (['admin_transactions', 'company_payment_settings', 'company_subscriptions', 'kmoney_category_rules', 'payments', 'reviews'] as $table) {
            $this->delete($table, 'company_id', $companyIds);
        }

        $this->delete('payments', 'user_id', $userIds);
        $this->delete('sessions', 'user_id', $userIds);

        // Un inserzionista resta, come quando si cancella un'azienda dal sito.
        if ($companyIds) {
            DB::table('advertisers')->whereIn('company_id', $companyIds)->update(['company_id' => null]);
        }
        if ($userIds) {
            DB::table('advertisers')->whereIn('user_id', $userIds)->update(['user_id' => null]);
        }

        $removedCompanies = $this->delete('companies', 'id', $companyIds);
        $users = $this->delete('users', 'id', $userIds);

        $brandIds = DB::table('product_brands')->whereIn('slug', self::BRAND_SLUGS)->pluck('id')->all();

        // I prodotti veri di una marca di prova restano, senza marca.
        if ($brandIds) {
            DB::table('products')->whereIn('brand_id', $brandIds)->update(['brand_id' => null]);
        }

        return [
            'companies' => $removedCompanies,
            'users' => $users,
            'products' => $products,
            'brands' => $this->delete('product_brands', 'id', $brandIds),
        ];
    }

    private function delete(string $table, string $column, array $ids): int
    {
        return $ids && Schema::hasTable($table)
            ? DB::table($table)->whereIn($column, $ids)->delete()
            : 0;
    }
}
