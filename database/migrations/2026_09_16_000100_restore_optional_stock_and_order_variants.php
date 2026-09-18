<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock')->nullable()->default(null)->change();
        });
        Schema::table('order_items', function (Blueprint $table) {
            // Keep the purchased variant ID even if the vendor later removes it.
            $table->unsignedBigInteger('product_variant_id')->nullable();
        });
    }

    public function down(): void
    {
        DB::table('products')->whereNull('stock')->update(['stock' => 0]);
        Schema::table('products', fn (Blueprint $table) => $table->integer('stock')->nullable(false)->default(0)->change());
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('product_variant_id'));
    }
};
