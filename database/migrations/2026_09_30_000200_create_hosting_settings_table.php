<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Server e pannello dell'hosting scelti in Amministrazione, Domini, Server
 * e hosting: prendono il posto delle righe KSM_* del .env, cosi' un
 * trasloco (altro cPanel, altra WHM, VPS) si fa da li' e non a mano sul server.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosting_settings', function (Blueprint $table) {
            $table->id();
            // env: vale il .env; none: nessun pannello (VPS con Caddy); cpanel; whm.
            $table->string('panel', 10)->default('env');
            $table->string('server_ips')->nullable();
            $table->string('server_cname')->nullable();
            $table->string('cpanel_url')->nullable();
            $table->string('cpanel_user')->nullable();
            $table->text('cpanel_token')->nullable();
            $table->string('cpanel_docroot')->nullable();
            $table->string('whm_url')->nullable();
            $table->string('whm_reseller')->nullable();
            $table->text('whm_token')->nullable();
            $table->string('whm_account')->nullable();
            $table->string('whm_proxy_plan')->nullable();
            $table->string('whm_proxy_target')->nullable();
            $table->string('whm_contact_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_settings');
    }
};
