<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('website_name')->nullable();
            $table->string('website_url')->nullable();
            $table->string('website_email')->nullable();
            $table->string('support_email')->nullable();
            $table->string('site_logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('address')->nullable();
            $table->string('about')->nullable();
            $table->text('location_map_embed')->nullable();
            $table->json('social_links')->nullable();
            $table->decimal('base_shipping_rate', 10, 2)->nullable();
            $table->decimal('per_kg_rate', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_settings');
    }
};
