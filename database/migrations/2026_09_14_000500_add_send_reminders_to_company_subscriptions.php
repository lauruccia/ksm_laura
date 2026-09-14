<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promemoria di rinnovo spegnibili per abbonamento.
 *
 * Le aziende importate le ha inserite Gruppo Kosmos, non le hanno pagate:
 * mandare loro un invito a rinnovare sarebbe posta indesiderata. Scadono
 * lo stesso, in silenzio, e si rinnovano in blocco da amministrazione.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->boolean('send_reminders')->default(true)->after('reminders_sent');
            // Il giro dei rinnovi e il rinnovo in blocco cercano per stato e scadenza.
            $table->index(['status', 'ends_at']);
        });

        DB::table('company_subscriptions')
            ->where('notes', 'Importato dal database originale')
            ->update(['send_reminders' => false]);
    }

    public function down(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['status', 'ends_at']);
            $table->dropColumn('send_reminders');
        });
    }
};
