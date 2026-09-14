<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Il bonifico non ha un gestore da interrogare: l'incasso lo
     * conferma l'amministratore quando vede i soldi sul conto.
     */
    public function up(): void
    {
        Schema::table('admin_payment_settings', function (Blueprint $table) {
            $table->boolean('enable_bank_transfer')->default(false)->after('enable_kmoney');
            $table->string('bank_holder')->nullable();
            $table->string('bank_iban')->nullable();
            $table->string('bank_bic')->nullable();
            $table->text('bank_instructions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('admin_payment_settings', function (Blueprint $table) {
            $table->dropColumn(['enable_bank_transfer', 'bank_holder', 'bank_iban', 'bank_bic', 'bank_instructions']);
        });
    }
};
