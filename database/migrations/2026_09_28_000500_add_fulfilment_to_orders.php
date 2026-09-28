<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Gestione degli ordini dall'amministrazione:
 *
 *  - stock_deducted_at: quando la disponibilita' dei prodotti e' stata
 *    scalata. Un ordine annullato la restituisce, uno riattivato la
 *    riprende, e mai due volte. Gli ordini gia' pagati, spediti o conclusi
 *    l'hanno scalata al pagamento;
 *  - corriere, numero e link di tracciamento, data di spedizione;
 *  - note interne, che il cliente non vede;
 *  - user_id facoltativo: un ordine inserito a mano puo' essere di un
 *    cliente senza account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('stock_deducted_at')->nullable()->after('status');
            $table->string('carrier', 120)->nullable()->after('notes');
            $table->string('tracking_number', 120)->nullable()->after('carrier');
            $table->string('tracking_url', 500)->nullable()->after('tracking_number');
            $table->timestamp('shipped_at')->nullable()->after('tracking_url');
            $table->text('admin_notes')->nullable()->after('shipped_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        DB::table('orders')->whereIn('status', ['paid', 'shipped', 'completed'])->update(['stock_deducted_at' => DB::raw('updated_at')]);
        DB::table('orders')->where('status', 'shipped')->update(['shipped_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        // Gli ordini senza account non avrebbero piu' posto: restano solo quelli con un cliente.
        DB::table('orders')->whereNull('user_id')->delete();

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['stock_deducted_at', 'carrier', 'tracking_number', 'tracking_url', 'shipped_at', 'admin_notes']);
        });
    }
};
