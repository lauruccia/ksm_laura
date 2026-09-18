<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Il telefono importato non e' un numero solo: fisso, cellulare e fax
     * finiscono sullo stesso campo e i 50 caratteri non bastavano. La
     * migration originale e' gia' girata sul database online, quindi la
     * colonna la si allarga qui.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 50)->nullable()->change();
        });
    }
};
