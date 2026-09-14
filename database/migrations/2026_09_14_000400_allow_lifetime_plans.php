<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Piani senza scadenza.
 *
 * `duration_days` vuoto vuol dire che il piano, una volta attivo, non
 * scade mai: Ecommerce, Vetrina e Biglietto funzionano cosi'. Anagrafica
 * resta annuale e si rinnova.
 */
return new class extends Migration
{
    /** Piani del sito originale che non scadono. */
    private const LIFETIME = ['ecommerce', 'vetrina', 'biglietto'];

    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_days')->nullable()->default(365)->change();
        });

        $plans = DB::table('plans')->whereIn('slug', self::LIFETIME)->pluck('id');

        DB::table('plans')->whereIn('id', $plans)->update(['duration_days' => null]);

        // Gli abbonamenti in corso di questi piani perdono la scadenza, e
        // con lei i promemoria gia' segnati.
        DB::table('company_subscriptions')
            ->whereIn('plan_id', $plans)
            ->where('status', 'active')
            ->update(['ends_at' => null, 'reminders_sent' => null]);
    }

    /** Le scadenze tolte non si possono ricostruire: tornano solo i piani annuali. */
    public function down(): void
    {
        DB::table('plans')->whereNull('duration_days')->update(['duration_days' => 365]);

        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_days')->nullable(false)->default(365)->change();
        });
    }
};
