<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Ogni pagina CMS appartiene a un sito: domain_id vuoto e' il sito
 * principale (KSM), altrimenti un dominio della rete. Lo slug e' unico
 * dentro il sito, non piu' in tutto il database: due domini possono
 * avere ciascuno la sua "chi-siamo".
 *
 * Una pagina scelta come pagina iniziale di un dominio ne riceve una copia
 * sua, e il dominio punta alla copia: l'originale resta di KSM, cosi'
 * menu e link del sito principale continuano a funzionare. Le altre
 * pagine restano di KSM e si spostano dall'amministrazione.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            // Eliminato il dominio, le sue pagine vanno con lui: non devono diventare di KSM.
            $table->foreignId('domain_id')->nullable()->after('id')->constrained('domains')->cascadeOnDelete();
        });

        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['domain_id', 'slug']);
        });

        $domains = DB::table('domains')->whereNotNull('entry_cms_page_id')->get(['id', 'entry_cms_page_id']);

        foreach ($domains as $domain) {
            $page = (array) DB::table('cms_pages')->where('id', $domain->entry_cms_page_id)->first();

            if (! $page) {
                continue;
            }

            unset($page['id']);
            $copy = DB::table('cms_pages')->insertGetId(['domain_id' => $domain->id] + $page);

            DB::table('domains')->where('id', $domain->id)->update(['entry_cms_page_id' => $copy]);
        }
    }

    public function down(): void
    {
        // Lo slug torna unico in tutto il database: le pagine dei domini con uno
        // slug gia' usato prendono in coda l'id del dominio, invece di sparire.
        foreach (DB::table('cms_pages')->whereNotNull('domain_id')->orderBy('id')->get(['id', 'slug', 'domain_id']) as $page) {
            if (DB::table('cms_pages')->where('slug', $page->slug)->where('id', '!=', $page->id)->exists()) {
                DB::table('cms_pages')->where('id', $page->id)->update(['slug' => $page->slug.'-'.$page->domain_id]);
            }
        }

        // Prima la chiave esterna: MySQL usa l'indice unico per domain_id.
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropForeign(['domain_id']);
            $table->dropUnique(['domain_id', 'slug']);
            $table->dropColumn('domain_id');
        });

        Schema::table('cms_pages', function (Blueprint $table) {
            $table->unique('slug');
        });
    }
};
