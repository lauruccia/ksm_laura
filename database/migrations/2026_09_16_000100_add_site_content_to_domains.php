<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Un dominio della rete diventa un sito a se': pagina di ingresso, aziende
 * mostrate, contenuti dei blocchi, piede e SEO. I contenuti stanno in un
 * solo campo JSON, `site`, letto da App\Support\Sites\SiteContent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            // home | shop | companies | company | page
            $table->string('entry_page')->default('home');
            $table->foreignId('entry_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('entry_cms_page_id')->nullable()->constrained('cms_pages')->nullOnDelete();
            // category: per categoria azienda; products: chi vende nella categoria prodotto del dominio.
            $table->string('company_scope')->default('category');
            $table->string('favicon')->nullable();
            $table->longText('site')->nullable();
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('image')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', fn (Blueprint $table) => $table->dropColumn('image'));

        Schema::table('domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entry_company_id');
            $table->dropConstrainedForeignId('entry_cms_page_id');
            $table->dropColumn(['entry_page', 'company_scope', 'favicon', 'site']);
        });
    }
};
