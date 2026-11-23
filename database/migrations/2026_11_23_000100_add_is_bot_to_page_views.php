<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Il contrassegno "visita non umana", scritto dal giro `analytics:classify`.
 * Prima si ricalcolava a ogni conteggio con una sottoquery sulle sessioni: con
 * centinaia di migliaia di righe la pagina Statistiche impiegava oltre un minuto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->boolean('is_bot')->default(false);

            $table->index(['day', 'is_bot']);
            $table->index(['host', 'day', 'is_bot']);
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex(['day', 'is_bot']);
            $table->dropIndex(['host', 'day', 'is_bot']);
            $table->dropColumn('is_bot');
        });
    }
};
