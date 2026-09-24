<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stato commerciale del conto KMoney del venditore, letto dall'API.
 *
 * GET /balance dice se il conto e' in debito, se puo' vendere e con
 * quali quote KY. Con il token del venditore il debito non lo imposta
 * piu' l'amministrazione: lo aggiorna KMoney.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_payment_settings', function (Blueprint $table) {
            $table->boolean('kmoney_can_sell')->nullable();
            $table->json('kmoney_allowed_percentages')->nullable();
            $table->timestamp('kmoney_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_payment_settings', fn (Blueprint $table) => $table->dropColumn([
            'kmoney_can_sell', 'kmoney_allowed_percentages', 'kmoney_synced_at',
        ]));
    }
};
