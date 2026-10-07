<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Statistiche delle visite.
 *
 * Una riga per ogni pagina vista sul sito pubblico, di qualsiasi dominio.
 * Niente cookie e niente dati personali: l'indirizzo IP non si salva, resta
 * solo un'impronta che cambia ogni giorno (`visitor`) per contare le
 * persone senza poterle riconoscere da un giorno all'altro.
 *
 * `day` e `hour` sono l'ora locale del sito (Europe/Rome) scritta al momento
 * della visita: cosi' i raggruppamenti per giorno e per ora non dipendono dal
 * fuso del database e restano giusti anche con l'ora legale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            // Sigla casuale che la pagina rimanda con la durata: l'unico modo per aggiornare la riga.
            $table->string('uid', 26)->unique();
            $table->string('host', 190);
            // platform | domain | company, e l'id del dominio o dell'azienda.
            $table->string('site_type', 10)->default('platform');
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('path', 255);
            $table->string('session_id', 26);
            $table->string('visitor', 40);
            // Prima pagina della visita: porta con se' la provenienza.
            $table->boolean('is_entry')->default(false);
            // direct | search | social | email | network | referral | campaign
            $table->string('channel', 12)->nullable();
            $table->string('source', 120)->nullable();
            $table->string('medium', 60)->nullable();
            $table->string('campaign', 120)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('device', 10);
            $table->string('browser', 30);
            $table->string('os', 30);
            // Secondi in cui la pagina e' rimasta davanti agli occhi di chi la guarda.
            $table->unsignedSmallInteger('duration')->default(0);
            $table->date('day');
            $table->unsignedTinyInteger('hour');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['host', 'day']);
            $table->index('day');
            $table->index('session_id');
            $table->index(['visitor', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
