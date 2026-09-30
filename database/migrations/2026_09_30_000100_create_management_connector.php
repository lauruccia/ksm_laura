<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PUBLIC_TABLES = ['companies', 'products', 'product_variants', 'orders', 'order_items'];

    public function up(): void
    {
        foreach (self::PUBLIC_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->uuid('public_id')->nullable()->unique();
                $table->unsignedBigInteger('integration_version')->default(1);
            });

            DB::table($tableName)->orderBy('id')->eachById(function ($row) use ($tableName): void {
                DB::table($tableName)->where('id', $row->id)->update(['public_id' => (string) Str::uuid()]);
            });
        }

        Schema::create('management_api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 100);
            $table->char('token_hash', 64)->unique();
            $table->string('token_prefix', 12)->index();
            $table->json('scopes');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('management_api_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('key');
            $table->string('operation', 80);
            $table->uuid('resource_id');
            $table->char('request_hash', 64);
            $table->json('request_body');
            $table->unsignedSmallInteger('status_code');
            $table->json('response_body');
            $table->timestamps();
            $table->unique(['company_id', 'operation', 'key'], 'management_idempotency_unique');
        });

        Schema::create('management_webhook_outbox', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('resource_type', 40);
            $table->uuid('resource_id');
            $table->longText('body');
            $table->timestamp('occurred_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_webhook_outbox');
        Schema::dropIfExists('management_api_idempotency_keys');
        Schema::dropIfExists('management_api_tokens');

        foreach (array_reverse(self::PUBLIC_TABLES) as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['public_id', 'integration_version']);
            });
        }
    }
};
