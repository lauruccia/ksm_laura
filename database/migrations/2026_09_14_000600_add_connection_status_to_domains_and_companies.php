<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stato di collegamento dei domini: quello che sul vecchio sito era "Connected".
 *
 * Vale per i domini della rete (`domains`) e per i domini propri delle
 * aziende (`companies.custom_domain`). Un dominio e' collegato quando il
 * DNS punta al server e il certificato risponde valido.
 */
return new class extends Migration
{
    private const TABLES = ['domains', 'companies'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('dns_verified_at')->nullable();
                $table->timestamp('ssl_verified_at')->nullable();
                $table->timestamp('domain_checked_at')->nullable();
                $table->string('domain_error')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['dns_verified_at', 'ssl_verified_at', 'domain_checked_at', 'domain_error']);
            });
        }
    }
};
