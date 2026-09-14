<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circuito banner.
 *
 * Gli inserzionisti sono aziende del sito o esterni con un accesso loro.
 * Ogni campagna (`advertisements`) compare nelle posizioni scelte, sui
 * domini, nelle citta' e nelle categorie scelte, e si paga a periodo, a
 * visualizzazioni o a clic. Visualizzazioni e clic si sommano per giorno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisers', function (Blueprint $table) {
            $table->id();
            // Esterno con accesso proprio, oppure azienda del sito: mai tutti e due.
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('vat_number', 32)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('advertisements', function (Blueprint $table) {
            $table->unsignedBigInteger('advertiser_id')->nullable()->index();
            $table->string('billing')->default('period');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_impressions')->nullable();
            $table->unsignedInteger('max_clicks')->nullable();
            $table->unsignedBigInteger('impressions')->default(0);
            // Vuoto vuol dire ovunque.
            $table->json('target_domains')->nullable();
            $table->json('target_cities')->nullable();
            $table->json('target_categories')->nullable();
        });

        Schema::create('advertisement_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertisement_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();
            $table->unique(['advertisement_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisement_stats');

        Schema::table('advertisements', function (Blueprint $table) {
            $table->dropIndex(['advertiser_id']);
            $table->dropColumn([
                'advertiser_id', 'billing', 'starts_at', 'ends_at', 'max_impressions', 'max_clicks',
                'impressions', 'target_domains', 'target_cities', 'target_categories',
            ]);
        });

        Schema::dropIfExists('advertisers');
    }
};
