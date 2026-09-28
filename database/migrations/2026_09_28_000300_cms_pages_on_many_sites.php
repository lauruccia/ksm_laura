<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Una pagina CMS puo' stare su piu' siti: il sito principale
 * (cms_pages.on_platform) e i domini della rete scelti (cms_page_domain).
 * Prende il posto di cms_pages.domain_id, che ne ammetteva uno solo:
 * ogni pagina resta dove era.
 *
 * Lo slug non e' piu' unico nel database: lo controlla l'amministrazione,
 * che non lascia due pagine con lo stesso slug su uno stesso sito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_page_domain', function (Blueprint $table) {
            $table->foreignId('cms_page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->primary(['cms_page_id', 'domain_id']);
        });

        Schema::table('cms_pages', function (Blueprint $table) {
            $table->boolean('on_platform')->default(true)->after('id');
        });

        foreach (DB::table('cms_pages')->whereNotNull('domain_id')->get(['id', 'domain_id']) as $page) {
            DB::table('cms_page_domain')->insert(['cms_page_id' => $page->id, 'domain_id' => $page->domain_id]);
            DB::table('cms_pages')->where('id', $page->id)->update(['on_platform' => false]);
        }

        // Prima la chiave esterna: MySQL usa l'indice unico per domain_id.
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropForeign(['domain_id']);
            $table->dropUnique(['domain_id', 'slug']);
        });

        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropColumn('domain_id');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->foreignId('domain_id')->nullable()->after('id')->constrained('domains')->cascadeOnDelete();
        });

        // Di ogni pagina resta un sito solo: il primo dominio, se non era del sito principale.
        foreach (DB::table('cms_pages')->where('on_platform', false)->get(['id']) as $page) {
            $domain = DB::table('cms_page_domain')->where('cms_page_id', $page->id)->min('domain_id');
            DB::table('cms_pages')->where('id', $page->id)->update(['domain_id' => $domain]);
        }

        // Due pagine con lo stesso slug sullo stesso sito: la seconda prende l'id in coda.
        foreach (DB::table('cms_pages')->orderBy('id')->get(['id', 'slug', 'domain_id']) as $page) {
            $taken = DB::table('cms_pages')->where('slug', $page->slug)->where('id', '<', $page->id)
                ->where(fn ($q) => $page->domain_id ? $q->where('domain_id', $page->domain_id) : $q->whereNull('domain_id'))
                ->exists();

            if ($taken) {
                DB::table('cms_pages')->where('id', $page->id)->update(['slug' => $page->slug.'-'.$page->id]);
            }
        }

        Schema::table('cms_pages', function (Blueprint $table) {
            $table->unique(['domain_id', 'slug']);
            $table->dropColumn('on_platform');
        });

        Schema::dropIfExists('cms_page_domain');
    }
};
