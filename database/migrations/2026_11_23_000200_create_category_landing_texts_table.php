<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // I testi scritti a mano per le pagine /categorie/{categoria}[/{luogo}].
        // place_key: '' = la pagina della categoria, '*' = modello per tutti i luoghi
        // della categoria, altrimenti l'indirizzo del luogo (per esempio 'roma').
        Schema::create('category_landing_texts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_category_id')->constrained('company_categories')->cascadeOnDelete();
            $table->string('place_key', 120)->default('');
            $table->string('heading')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->text('intro')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();

            $table->unique(['company_category_id', 'place_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_landing_texts');
    }
};
