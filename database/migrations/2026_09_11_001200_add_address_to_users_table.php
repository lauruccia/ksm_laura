<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'indirizzo abituale di chi acquista.
     *
     * Sta sull'utente e non sull'ordine: l'ordine conserva l'indirizzo
     * com'era quel giorno, questo serve solo a non farlo riscrivere
     * ogni volta in cassa.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('billing_address', 500)->nullable()->after('phone');
            $table->string('billing_city', 120)->nullable()->after('billing_address');
            $table->string('billing_state', 120)->nullable()->after('billing_city');
            $table->string('billing_zip', 20)->nullable()->after('billing_state');
            $table->string('billing_country', 120)->nullable()->after('billing_zip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'billing_address', 'billing_city', 'billing_state', 'billing_zip', 'billing_country',
            ]);
        });
    }
};
