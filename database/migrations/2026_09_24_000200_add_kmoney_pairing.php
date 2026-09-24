<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collegamento a KMoney con il solo numero di conto.
 *
 * Il venditore scrive il numero, l'amministrazione KMoney approva, e
 * token e segreto del webhook arrivano da soli. Il segreto del ritiro
 * lo genera KSM, e serve solo finche' il collegamento e' in attesa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_payment_settings', function (Blueprint $table) {
            $table->string('kmoney_account_number', 16)->nullable();
            $table->string('kmoney_pairing_uuid', 64)->nullable();
            $table->text('kmoney_pairing_secret')->nullable();
            // pending, approved, rejected, lost (approvato ma ritirato altrove).
            $table->string('kmoney_pairing_status', 16)->nullable()->index();
            $table->timestamp('kmoney_pairing_requested_at')->nullable();
            $table->timestamp('kmoney_pairing_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_payment_settings', function (Blueprint $table) {
            $table->dropIndex(['kmoney_pairing_status']);
            $table->dropColumn([
                'kmoney_account_number', 'kmoney_pairing_uuid', 'kmoney_pairing_secret',
                'kmoney_pairing_status', 'kmoney_pairing_requested_at', 'kmoney_pairing_checked_at',
            ]);
        });
    }
};
