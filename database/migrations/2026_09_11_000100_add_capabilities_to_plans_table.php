<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le voci in `features` restano il testo mostrato in vetrina.
     * Quello che il piano permette davvero sta in `capabilities`:
     * e' l'elenco delle chiavi spuntate in amministrazione.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('capabilities')->nullable()->after('features');
            $table->unsignedSmallInteger('duration_days')->default(365)->after('price');
            $table->boolean('is_active')->default(true)->after('duration_days');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['capabilities', 'duration_days', 'is_active']);
        });
    }
};
