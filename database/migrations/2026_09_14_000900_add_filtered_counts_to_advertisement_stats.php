<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visualizzazioni e clic scartati perche' arrivati da programmi automatici.
 *
 * Non valgono per i limiti ne' per il conto, ma restano scritti: chi
 * compra una campagna puo' vedere quanto e' stato tolto, e perche'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertisement_stats', function (Blueprint $table) {
            $table->unsignedInteger('filtered_impressions')->default(0);
            $table->unsignedInteger('filtered_clicks')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('advertisement_stats', function (Blueprint $table) {
            $table->dropColumn(['filtered_impressions', 'filtered_clicks']);
        });
    }
};
