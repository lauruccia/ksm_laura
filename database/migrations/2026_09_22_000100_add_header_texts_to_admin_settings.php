<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sottotitolo e motto sotto il marchio del sito principale.
     * Vuoti: i testi predefiniti delle traduzioni.
     */
    public function up(): void
    {
        Schema::table('admin_settings', function (Blueprint $table) {
            $table->string('header_tagline')->nullable()->after('about');
            $table->string('header_subline')->nullable()->after('header_tagline');
        });
    }

    public function down(): void
    {
        Schema::table('admin_settings', function (Blueprint $table) {
            $table->dropColumn(['header_tagline', 'header_subline']);
        });
    }
};
