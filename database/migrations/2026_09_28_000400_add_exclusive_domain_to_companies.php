<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Un'azienda puo' stare su un solo dominio della rete: exclusive_domain_id.
 * Allora lei e i suoi prodotti si vedono su quel dominio (anche fuori dal
 * suo filtro) e sul suo dominio proprio, non su KSM ne' sugli altri
 * domini. Vuoto: come prima, ovunque il filtro la comprenda.
 *
 * Eliminare il dominio spegne le sue aziende esclusive (Domain::booted):
 * non devono ritrovarsi su KSM senza che nessuno l'abbia deciso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('exclusive_domain_id')->nullable()->constrained('domains')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exclusive_domain_id');
        });
    }
};
