<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ManagementApiToken;
use App\Models\ManagementWebhookOutbox;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_is_scoped_to_one_company_and_one_capability(): void
    {
        [$company, $raw] = $this->companyWithToken(['identity:read']);
        $other = $this->company('Altra azienda');
        $otherProduct = $this->product($other, ['name' => 'Segreto']);

        $this->withToken($raw)->getJson('/api/management/v1/identity')
            ->assertOk()
            ->assertJsonPath('data.company_id', $company->public_id)
            ->assertJsonPath('data.capabilities.0', 'identity:read')
            ->assertHeader('X-Correlation-ID');

        $this->withToken($raw)->getJson('/api/management/v1/products')->assertForbidden()
            ->assertJsonPath('error.code', 'insufficient_scope');

        [, $catalogToken] = ManagementApiToken::issue($company, 'catalogo', ['catalog:read']);
        $this->withToken($catalogToken)->getJson('/api/management/v1/products/'.$otherProduct->public_id)
            ->assertNotFound()->assertJsonPath('error.code', 'not_found');
    }

    public function test_expired_and_missing_tokens_are_rejected(): void
    {
        [$company, $raw] = $this->companyWithToken(['identity:read']);
        ManagementApiToken::query()->where('company_id', $company->id)->update(['expires_at' => now()->subMinute()]);

        $this->getJson('/api/management/v1/identity')->assertUnauthorized();
        $this->withToken($raw)->getJson('/api/management/v1/identity')->assertUnauthorized();
    }

    public function test_token_command_accepts_the_numeric_company_id(): void
    {
        $company = $this->company();

        $this->artisan('management:issue-token', [
            'company' => (string) $company->id,
            'name' => 'Gestionale pilota',
            '--scope' => ['catalog:read'],
            '--days' => '30',
        ])->assertSuccessful();

        $this->assertDatabaseHas('management_api_tokens', [
            'company_id' => $company->id,
            'name' => 'Gestionale pilota',
        ]);
    }

    public function test_catalog_uses_public_ids_exact_cents_and_stable_cursor_pagination(): void
    {
        [$company, $raw] = $this->companyWithToken(['catalog:read']);
        $first = $this->product($company, ['name' => 'Primo', 'price' => '12.34', 'discount_price' => '10.05', 'stock' => null]);
        $second = $this->product($company, ['name' => 'Secondo', 'price' => '2.30', 'stock' => 0]);
        ProductVariant::query()->create([
            'product_id' => $first->id,
            'variant_type' => 'Colore',
            'variant_value' => 'Blu',
            'variant_price' => '11.25',
            'variant_stock' => '3',
            'variant_sku' => 'BLU-1',
            'attributes' => json_encode(['Colore' => 'Blu']),
        ]);
        $stamp = Carbon::parse('2026-09-30 10:00:00');
        Product::query()->whereKey([$first->id, $second->id])->update(['updated_at' => $stamp]);

        $pageOne = $this->withToken($raw)->getJson('/api/management/v1/products?limit=1')->assertOk()
            ->assertJsonPath('data.0.id', $first->public_id)
            ->assertJsonPath('data.0.price_cents', 1005)
            ->assertJsonPath('data.0.stock_managed', false)
            ->assertJsonPath('data.0.variants.0.price_cents', 1125)
            ->assertJsonPath('meta.has_more', true);

        $cursor = $pageOne->json('meta.next_cursor');
        $this->withToken($raw)->getJson('/api/management/v1/products?limit=1&cursor='.urlencode($cursor))
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->public_id)
            ->assertJsonPath('data.0.stock_managed', true)
            ->assertJsonPath('data.0.available_quantity', 0)
            ->assertJsonPath('meta.has_more', false);

        $this->withToken($raw)->getJson('/api/management/v1/products?cursor=broken')
            ->assertStatus(422)->assertJsonPath('error.code', 'invalid_cursor');
    }

    public function test_orders_export_business_data_without_customer_or_transaction_identifiers(): void
    {
        [$company, $raw] = $this->companyWithToken(['orders:read']);
        $product = $this->product($company, ['sku' => 'SKU-10', 'price' => '12.34']);
        $payment = Payment::query()->create([
            'company_id' => $company->id,
            'method' => 'stripe',
            'amount' => '24.68',
            'currency' => 'EUR',
            'transaction_id' => 'private-transaction-id',
            'status' => 'completed',
        ]);
        $order = Order::query()->create([
            'company_id' => $company->id,
            'payment_id' => $payment->id,
            'subtotal' => '24.68',
            'shipping' => '4.00',
            'tax' => '0.00',
            'total' => '28.68',
            'currency' => 'EUR',
            'status' => 'paid',
            'billing_name' => 'Persona Privata',
            'billing_email' => 'private@example.test',
        ]);
        $order->forceFill(['stock_deducted_at' => now()])->save();
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Prodotto',
            'product_price' => '12.34',
            'quantity' => 2,
            'subtotal' => '24.68',
        ]);

        $response = $this->withToken($raw)->getJson('/api/management/v1/orders/'.$order->public_id)
            ->assertOk()
            ->assertJsonPath('data.total_cents', 2868)
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.stock_effect', 'deducted')
            ->assertJsonPath('data.lines.0.product_id', $product->public_id)
            ->assertJsonPath('data.payment_allocations.0.amount_cents', 2468);

        $payload = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Persona Privata', $payload);
        $this->assertStringNotContainsString('private@example.test', $payload);
        $this->assertStringNotContainsString('private-transaction-id', $payload);
    }

    public function test_inventory_write_checks_version_tenant_and_idempotency(): void
    {
        [$company, $raw] = $this->companyWithToken(['inventory:write']);
        $product = $this->product($company, ['stock' => 5]);
        $otherProduct = $this->product($this->company('Altra'), ['stock' => 9]);
        ManagementWebhookOutbox::query()->delete();
        $key = (string) str()->uuid();
        $payload = ['available_quantity' => 7, 'expected_version' => 1, 'reason' => 'Riconciliazione'];

        $this->withToken($raw)->withHeader('Idempotency-Key', $key)
            ->putJson('/api/management/v1/inventory/'.$product->public_id, $payload)
            ->assertOk()
            ->assertJsonPath('data.available_quantity', 7)
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 7, 'integration_version' => 2]);
        $events = ManagementWebhookOutbox::query()->count();

        $this->withToken($raw)->withHeader('Idempotency-Key', $key)
            ->putJson('/api/management/v1/inventory/'.$product->public_id, $payload)
            ->assertOk()->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($events, ManagementWebhookOutbox::query()->count());

        $this->withToken($raw)->withHeader('Idempotency-Key', $key)
            ->putJson('/api/management/v1/inventory/'.$product->public_id, array_merge($payload, ['available_quantity' => 8]))
            ->assertConflict()->assertJsonPath('error.code', 'idempotency_conflict');

        $this->withToken($raw)->withHeader('Idempotency-Key', (string) str()->uuid())
            ->putJson('/api/management/v1/inventory/'.$product->public_id, $payload)
            ->assertConflict()->assertJsonPath('error.code', 'version_conflict')
            ->assertJsonPath('error.current_version', 2);

        $this->withToken($raw)->withHeader('Idempotency-Key', (string) str()->uuid())
            ->putJson('/api/management/v1/inventory/'.$otherProduct->public_id, $payload)
            ->assertNotFound();
    }

    public function test_fulfillment_is_idempotent_and_moves_stock_once(): void
    {
        [$company, $raw] = $this->companyWithToken(['fulfillments:write']);
        $product = $this->product($company, ['stock' => 5]);
        $order = Order::query()->create([
            'company_id' => $company->id,
            'subtotal' => '20.00',
            'shipping' => '0.00',
            'tax' => '0.00',
            'total' => '20.00',
            'currency' => 'EUR',
            'status' => 'paid',
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Prodotto',
            'product_price' => '10.00',
            'quantity' => 2,
            'subtotal' => '20.00',
        ]);
        ManagementWebhookOutbox::query()->delete();
        $key = (string) str()->uuid();
        $payload = [
            'expected_version' => 1,
            'carrier' => 'Corriere Test',
            'tracking_number' => 'TRACK-1',
            'tracking_url' => 'https://tracking.example/TRACK-1',
            'shipped_at' => '2026-09-30T12:30:00+02:00',
        ];

        $first = $this->withToken($raw)->withHeader('Idempotency-Key', $key)
            ->postJson('/api/management/v1/orders/'.$order->public_id.'/fulfillments', $payload)
            ->assertCreated()
            ->assertJsonPath('data.commercial_status', 'shipped')
            ->assertJsonPath('data.shipped_at', '2026-09-30T10:30:00.000000Z');

        $version = $first->json('data.version');
        $this->assertGreaterThan(1, $version);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
        $this->assertDatabaseHas('management_webhook_outbox', ['event_type' => 'fulfillment.updated']);

        $this->withToken($raw)->withHeader('Idempotency-Key', $key)
            ->postJson('/api/management/v1/orders/'.$order->public_id.'/fulfillments', $payload)
            ->assertCreated()->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('data.version', $version);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
    }

    private function companyWithToken(array $scopes): array
    {
        $company = $this->company();
        [, $raw] = ManagementApiToken::issue($company, 'test', $scopes);

        return [$company, $raw];
    }

    private function company(string $name = 'Azienda Test'): Company
    {
        $user = User::factory()->create();

        return Company::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(6),
            'is_active' => true,
        ]);
    }

    private function product(Company $company, array $attributes = []): Product
    {
        return Product::query()->create(array_merge([
            'company_id' => $company->id,
            'name' => 'Prodotto',
            'slug' => 'prodotto-'.str()->random(8),
            'sku' => 'SKU-'.str()->random(5),
            'price' => '10.00',
            'stock' => 5,
            'product_type' => 'simple',
            'status' => 'active',
        ], $attributes));
    }
}
