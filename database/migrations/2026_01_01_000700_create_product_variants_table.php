<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->text('variant_type')->nullable();
            $table->text('variant_value')->nullable();
            $table->text('variant_price')->nullable();
            $table->text('variant_stock')->nullable();
            $table->text('variant_sku')->nullable();
            $table->text('variant_images')->nullable();
            $table->text('attributes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
