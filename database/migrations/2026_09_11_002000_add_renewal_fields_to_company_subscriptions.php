<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dati del rinnovo.
     *
     * `reminders_sent` tiene le tappe gia' avvisate, cosi' il comando
     * giornaliero non manda due volte lo stesso promemoria. `replaces_id`
     * dice quale periodo un cambio di piano a meta' corsa sostituisce:
     * serve a ricostruire quanto e' stato scalato e perche'.
     */
    public function up(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->json('reminders_sent')->nullable()->after('notes');
            $table->foreignId('replaces_id')->nullable()->after('plan_id')
                ->constrained('company_subscriptions')->nullOnDelete();
            $table->decimal('credit', 10, 2)->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaces_id');
            $table->dropColumn(['reminders_sent', 'credit']);
        });
    }
};
