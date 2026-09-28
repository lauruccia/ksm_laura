<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Il sito su cui e' nato l'ordine: il sito principale (platform), un
 * dominio della rete (domain, con domain_id) o il dominio di un'azienda
 * (company, l'azienda e' gia' in company_id). Area cliente e traccia
 * ordine mostrano solo gli ordini del sito da cui si guarda.
 *
 * Gli ordini gia' presenti restano del sito principale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('site', 16)->default('platform')->after('company_id');
            $table->foreignId('domain_id')->nullable()->after('site')->constrained('domains')->nullOnDelete();
            // Non comincia da user_id: MySQL userebbe questo indice per la chiave esterna di user_id.
            $table->index(['site', 'domain_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['domain_id']);
            $table->dropIndex(['site', 'domain_id', 'user_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['site', 'domain_id']);
        });
    }
};
