<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamento diviso fra KMoney ed euro.
 *
 * Ogni prodotto si paga in parte in KMoney, secondo una quota di 0, 25,
 * 50, 75 o 100. La quota viene dal contratto KMoney del venditore, o da
 * una scelta per categoria o per prodotto; con il conto in debito e' 100.
 * La cassa incassa prima la quota KMoney, poi il resto in euro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_payment_settings', function (Blueprint $table) {
            // API KMoney v1: token del venditore e segreto delle notifiche firmate.
            $table->text('kmoney_api_token')->nullable();
            $table->text('kmoney_webhook_secret')->nullable();
            // Finche' l'API per leggerli non e' documentata, li imposta l'amministrazione.
            $table->unsignedTinyInteger('kmoney_contract_percent')->nullable();
            $table->boolean('kmoney_in_debt')->default(false);
        });

        Schema::table('admin_payment_settings', function (Blueprint $table) {
            $table->boolean('kmoney_euro_fallback')->default(false);
        });

        Schema::table('products', function (Blueprint $table) {
            // Quota effettiva, ricalcolata: serve a schede e filtri, la cassa la rifa'.
            $table->unsignedTinyInteger('kmoney_percent')->default(0)->index();
        });

        Schema::create('kmoney_category_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('percent');
            $table->timestamps();
            $table->unique(['company_id', 'product_category_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('kmoney_payment_id')->nullable()->index();
            $table->decimal('kmoney_total', 10, 2)->default(0);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('kmoney_percent')->default(0);
            $table->decimal('kmoney_amount', 10, 2)->default(0);
        });

        // Quota di partenza: la scelta scritta sul prodotto, dove c'e'. Contratto
        // e categorie arrivano dall'amministrazione, e con loro il ricalcolo.
        DB::table('products')
            ->whereNotNull('kmoney_discount_percent')
            ->select('id', 'kmoney_discount_percent')
            ->orderBy('id')
            ->chunkById(500, function ($products) {
                foreach ($products as $product) {
                    DB::table('products')->where('id', $product->id)->update([
                        'kmoney_percent' => intdiv(max(0, min(100, (int) $product->kmoney_discount_percent)), 25) * 25,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['kmoney_percent', 'kmoney_amount']));

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['kmoney_payment_id']);
            $table->dropColumn(['kmoney_payment_id', 'kmoney_total']);
        });

        Schema::dropIfExists('kmoney_category_rules');

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['kmoney_percent']);
            $table->dropColumn('kmoney_percent');
        });

        Schema::table('admin_payment_settings', fn (Blueprint $table) => $table->dropColumn('kmoney_euro_fallback'));

        Schema::table('company_payment_settings', fn (Blueprint $table) => $table->dropColumn([
            'kmoney_api_token', 'kmoney_webhook_secret', 'kmoney_contract_percent', 'kmoney_in_debt',
        ]));
    }
};
