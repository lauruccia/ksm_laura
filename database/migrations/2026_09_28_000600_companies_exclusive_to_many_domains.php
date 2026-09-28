<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Un'azienda puo' stare su piu' domini della rete, non piu' uno solo:
 * company_exclusive_domain prende il posto di companies.exclusive_domain_id.
 * Con almeno un dominio scelto, lei e i suoi prodotti si vedono su quei
 * domini (anche fuori dal loro filtro) e sul suo dominio proprio, non su
 * KSM ne' sugli altri. Nessun dominio: come prima, ovunque il filtro la
 * comprenda. Ogni azienda resta dove era.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_exclusive_domain', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->primary(['company_id', 'domain_id']);
            // Le ricerche partono dal dominio: "chi e' esclusiva qui".
            $table->index('domain_id');
        });

        DB::table('companies')->whereNotNull('exclusive_domain_id')->orderBy('id')
            ->select(['id', 'exclusive_domain_id'])
            ->chunk(1000, function ($companies) {
                DB::table('company_exclusive_domain')->insert($companies->map(fn ($company) => [
                    'company_id' => $company->id,
                    'domain_id' => $company->exclusive_domain_id,
                ])->all());
            });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exclusive_domain_id');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('exclusive_domain_id')->nullable()->constrained('domains')->nullOnDelete();
        });

        // Di ogni azienda resta un dominio solo: il primo.
        foreach (DB::table('company_exclusive_domain')->groupBy('company_id')
            ->selectRaw('company_id, min(domain_id) as domain_id')->get() as $row) {
            DB::table('companies')->where('id', $row->company_id)->update(['exclusive_domain_id' => $row->domain_id]);
        }

        Schema::dropIfExists('company_exclusive_domain');
    }
};
