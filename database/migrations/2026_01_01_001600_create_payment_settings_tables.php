<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le credenziali dei gateway non stanno qui in chiaro:
     * la colonna contiene il riferimento alla chiave di ambiente.
     */
    public function up(): void
    {
        Schema::create('admin_payment_settings', function (Blueprint $table) {
            $table->id();
            $this->gatewayColumns($table);
            $table->timestamps();
        });

        Schema::create('company_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $this->gatewayColumns($table);
            $table->timestamps();
        });
    }

    private function gatewayColumns(Blueprint $table): void
    {
        $table->string('mode')->default('test');
        $table->boolean('enable_stripe')->default(false);
        $table->boolean('enable_paypal')->default(false);
        $table->boolean('enable_kmoney')->default(false);
        $table->string('stripe_test_public_key')->nullable();
        $table->text('stripe_test_secret_key')->nullable();
        $table->string('stripe_live_public_key')->nullable();
        $table->text('stripe_live_secret_key')->nullable();
        $table->string('paypal_test_client_id')->nullable();
        $table->text('paypal_test_secret')->nullable();
        $table->string('paypal_live_client_id')->nullable();
        $table->text('paypal_live_secret')->nullable();
        $table->string('kmoney_test_account')->nullable();
        $table->string('kmoney_live_account')->nullable();
        // Servono a verificare le notifiche dei gestori: sono per conto, non per sito.
        $table->text('stripe_webhook_secret')->nullable();
        $table->string('paypal_webhook_id')->nullable();
        $table->boolean('is_active')->default(true);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_payment_settings');
        Schema::dropIfExists('admin_payment_settings');
    }
};
