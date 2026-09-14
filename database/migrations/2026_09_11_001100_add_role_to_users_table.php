<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Il ruolo vale solo per chi sta in amministrazione. Gli amministratori
     * gia' presenti prendono il ruolo di sistema, altrimenti al primo
     * accesso non potrebbero piu' fare nulla.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('user_type')
                ->constrained('roles')->nullOnDelete();
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->boolean('is_active')->default(true)->after('user_type');
        });

        $roleId = DB::table('roles')->where('slug', 'super-admin')->value('id');

        if ($roleId) {
            DB::table('users')->where('user_type', 'admin')->update(['role_id' => $roleId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['last_login_at', 'is_active']);
        });
    }
};
