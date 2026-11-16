<?php

use App\Models\Domain;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Il filtro di un dominio a blocchi indipendenti: aziende, prodotti, luogo.
 *
 * Prima un menu "Filtro" accendeva o spegneva i campi, e "Aziende mostrate"
 * cambiava il senso delle categorie. Ora ogni campo dice una cosa sola e
 * vuoto vuol dire "nessun limite":
 *  - company_category_ids / product_category_ids: piu' categorie (JSON);
 *  - companies_with_products: solo aziende con prodotti in vendita;
 *  - product_scope: prodotti delle aziende mostrate, di tutto il
 *    marketplace, o nessuno;
 *  - filter_place: citta' o regione del filtro. `city` torna a essere solo
 *    la citta' dei recapiti nel piede.
 *
 * I domini esistenti si convertono con lo stesso risultato di prima. Le
 * colonne vecchie (company_category_id, product_category_id, company_scope)
 * restano nella tabella ma non si leggono piu'; `type` si ricava dai campi
 * nuovi a ogni salvataggio e serve solo all'elenco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->text('company_category_ids')->nullable();
            $table->text('product_category_ids')->nullable();
            $table->boolean('companies_with_products')->default(false);
            $table->string('product_scope', 20)->default('companies');
            $table->string('filter_place', 120)->nullable();
        });

        foreach (DB::table('domains')->get() as $row) {
            $filter = Domain::filterFromLegacy((array) $row);

            DB::table('domains')->where('id', $row->id)->update([
                'company_category_ids' => $filter['company_category_ids'] ? json_encode($filter['company_category_ids']) : null,
                'product_category_ids' => $filter['product_category_ids'] ? json_encode($filter['product_category_ids']) : null,
            ] + $filter);
        }
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['company_category_ids', 'product_category_ids', 'companies_with_products', 'product_scope', 'filter_place']);
        });
    }
};
