<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Regione della sede, usata dal filtro "Tutte le regioni" della ricerca. */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('region', 40)->nullable()->after('city')->index();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['region']);
            $table->dropColumn('region');
        });
    }
};
