<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indici misurati sulla directory con circa 98.000 aziende.
     *
     * `(region, is_active, plan_id)` prende il posto dell'indice sulla sola
     * regione, che ne e' il prefisso. Copre i filtri della directory: il
     * conteggio e l'ordine leggono l'indice invece delle righe intere, anche
     * senza filtro per regione (count da 72 a 14 ms, filtro Lazio da 28 a 2).
     *
     * `reviews.company_id` serve alla media delle recensioni di ogni scheda:
     * senza, SQLite scorre tutta la tabella per ogni azienda mostrata. Su
     * MySQL la chiave esterna ne crea gia' uno implicito, che questo sostituisce.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['region']);
            $table->index(['region', 'is_active', 'plan_id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['region', 'is_active', 'plan_id']);
            $table->index('region');
        });
    }
};
