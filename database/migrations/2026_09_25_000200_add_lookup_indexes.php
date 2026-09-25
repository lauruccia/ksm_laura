<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/*
 * Indici per le letture delle pagine pubbliche e dei pannelli: prodotti
 * attivi dal piu' recente, prodotti di un'azienda, varianti e recensioni di
 * un prodotto, ordini di un venditore. Su MySQL le chiavi esterne hanno gia'
 * il loro indice, su SQLite no: quello che c'e' gia' si salta.
 */
return new class extends Migration
{
    private const INDEXES = [
        'products' => [['status', 'created_at'], ['company_id', 'status'], ['category_id'], ['brand_id']],
        'product_variants' => [['product_id']],
        'product_reviews' => [['product_id']],
        'orders' => [['company_id', 'status']],
        'order_items' => [['order_id'], ['product_id']],
        'companies' => [['category_id'], ['plan_id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $columns) {
                if ($this->missing($table, $columns)) {
                    Schema::table($table, fn ($blueprint) => $blueprint->index($columns, $this->name($table, $columns)));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $columns) {
                if (Schema::hasIndex($table, $this->name($table, $columns))) {
                    Schema::table($table, fn ($blueprint) => $blueprint->dropIndex($this->name($table, $columns)));
                }
            }
        }
    }

    /** Manca se nessun indice esistente comincia con le stesse colonne. */
    private function missing(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumns($table, $columns)) {
            return false;
        }

        foreach (Schema::getIndexes($table) as $index) {
            if (array_slice($index['columns'], 0, count($columns)) === $columns) {
                return false;
            }
        }

        return true;
    }

    private function name(string $table, array $columns): string
    {
        return $table.'_'.implode('_', $columns).'_lookup';
    }
};
