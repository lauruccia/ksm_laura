<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('company_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->boolean('force_www')->default(false);
            $table->string('address')->nullable();
            $table->text('city')->nullable();
            $table->string('website')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('banner')->nullable();
            $table->string('logo')->nullable();
            $table->json('working_hours')->nullable();
            $table->json('offer_gallery')->nullable();
            $table->text('company_description')->nullable();
            $table->text('company_location')->nullable();
            $table->text('personal_page')->nullable();
            $table->boolean('is_active')->default(false);
            $table->decimal('base_shipping_rate', 10, 2)->nullable();
            $table->decimal('per_kg_rate', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
