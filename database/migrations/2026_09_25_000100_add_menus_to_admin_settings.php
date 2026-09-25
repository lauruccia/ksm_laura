<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu del sito principale per posizione (barra in alto, testata, piede),
     * scritti in Amministrazione, Menu. Vuoto: le voci predefinite.
     */
    public function up(): void
    {
        Schema::table('admin_settings', function (Blueprint $table) {
            $table->json('menus')->nullable()->after('social_links');
        });
    }

    public function down(): void
    {
        Schema::table('admin_settings', function (Blueprint $table) {
            $table->dropColumn('menus');
        });
    }
};
