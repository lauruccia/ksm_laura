<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statistiche piu' a fondo: obiettivi raggiunti, visitatori di ritorno,
 * regione e citta', parole cercate.
 *
 * - `conversions`: una riga per ogni obiettivo raggiunto (registrazione,
 *   prodotto nel carrello, ordine, messaggio inviato), legata alla visita in
 *   corso cosi' si sa da dove arrivava chi ha comprato o scritto.
 * - `page_views.vid`: impronta del cookie facoltativo dei visitatori di
 *   ritorno (spento di default, vedi KSM_ANALYTICS_RETURNING).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->string('region', 60)->nullable()->after('country');
            $table->string('city', 80)->nullable()->after('region');
            // Cosa ha scritto nella ricerca del sito (solo la prima pagina di risultati).
            $table->string('search', 100)->nullable()->after('path');
            // Parola cercata sul motore di ricerca (se il motore la passa) o parola chiave della campagna.
            $table->string('keyword', 100)->nullable()->after('campaign');
            $table->char('vid', 40)->nullable()->after('visitor');
            // Solo sulla prima pagina della visita; vuoto se il cookie e' spento.
            $table->boolean('is_returning')->nullable()->after('is_entry');

            $table->index('vid');
        });

        Schema::create('conversions', function (Blueprint $table) {
            $table->id();
            $table->string('host', 190);
            // Visita in corso quando l'obiettivo e' stato raggiunto (puo' mancare).
            $table->string('session_id', 26)->nullable();
            $table->string('visitor', 40)->nullable();
            // signup | cart | order | contact
            $table->string('goal', 12);
            // Importo dell'ordine, per gli obiettivi che ne hanno uno.
            $table->decimal('value', 12, 2)->nullable();
            // Id dell'ordine o dell'azienda a cui si riferisce.
            $table->string('ref', 40)->nullable();
            $table->string('channel', 12)->nullable();
            $table->string('source', 120)->nullable();
            $table->string('campaign', 120)->nullable();
            $table->char('country', 2)->nullable();
            $table->date('day');
            $table->unsignedTinyInteger('hour');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['host', 'day']);
            $table->index(['goal', 'day']);
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversions');

        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex(['vid']);
            $table->dropColumn(['region', 'city', 'search', 'keyword', 'vid', 'is_returning']);
        });
    }
};
