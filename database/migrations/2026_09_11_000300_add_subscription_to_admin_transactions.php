<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_transactions', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('plan_id')
                ->constrained('company_subscriptions')->nullOnDelete();
            $table->string('mode')->default('test')->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('admin_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn('mode');
        });
    }
};
