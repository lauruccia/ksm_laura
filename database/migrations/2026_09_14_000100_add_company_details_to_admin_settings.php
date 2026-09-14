<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dati del soggetto che gestisce il sito, mostrati nel piede.
     * La partita IVA e' obbligatoria sui siti delle imprese italiane.
     */
    public function up(): void
    {
        Schema::table('admin_settings', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('website_name');
            $table->string('vat_number', 32)->nullable()->after('company_name');
        });
    }

    public function down(): void
    {
        Schema::table('admin_settings', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'vat_number']);
        });
    }
};
