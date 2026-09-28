<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Domain;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Payments\PaymentCompletion;
use App\Support\Permissions;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gestione degli ordini: disponibilita' che segue lo stato, email al
 * cliente dal sito dove ha comprato, spedizione, modifica, inserimento a
 * mano, filtri ed esportazione.
 */
class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private const MAIN = 'http://localhost';

    private Company $company;

    private Product $cheese;

    private Product $wine;

    private ProductVariant $magnum;

    private Domain $domain;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::create([
            'name' => 'Completo', 'slug' => 'completo', 'price' => 99, 'priority' => 20, 'duration_days' => 365, 'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOWCASE, PlanCapabilities::SHOP],
        ]);
        $category = ProductCategory::create(['name' => 'Cibo', 'slug' => 'cibo']);
        $owner = User::create(['name' => 'Titolare', 'email' => 'titolare@caseificio.test', 'password' => 'password', 'user_type' => 'vendor']);

        $this->company = Company::create([
            'user_id' => $owner->id, 'plan_id' => $plan->id, 'name' => 'Caseificio Rossi', 'slug' => 'caseificio-rossi',
            'is_active' => true, 'email' => 'info@caseificio.test',
        ]);
        $this->cheese = Product::create([
            'company_id' => $this->company->id, 'category_id' => $category->id, 'name' => 'Pecorino',
            'slug' => 'pecorino', 'price' => 10, 'stock' => 20, 'status' => 'active',
        ]);
        $this->wine = Product::create([
            'company_id' => $this->company->id, 'category_id' => $category->id, 'name' => 'Vino',
            'slug' => 'vino', 'price' => 15, 'stock' => null, 'status' => 'active', 'product_type' => 'variable',
        ]);
        $this->magnum = ProductVariant::create(['product_id' => $this->wine->id, 'variant_type' => 'Formato', 'variant_value' => 'Magnum', 'variant_price' => 30, 'variant_stock' => 6]);

        $this->domain = Domain::create([
            'name' => 'Sapori di Calabria', 'domain' => 'saporicalabria.test', 'type' => 'home',
            'company_scope' => 'category', 'entry_page' => 'home', 'is_active' => true,
        ]);
        $this->buyer = User::create(['name' => 'Anna Bianchi', 'email' => 'anna@example.test', 'password' => 'password', 'user_type' => 'buyer', 'is_active' => true]);
    }

    private function admin(array $permissions = []): User
    {
        $role = $permissions
            ? Role::create(['slug' => 'ruolo'.uniqid(), 'name' => 'Ruolo', 'permissions' => $permissions])
            : Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super amministratore', 'is_system' => true]);

        return User::create([
            'name' => 'Amministratore', 'email' => 'admin'.uniqid().'@example.test', 'password' => 'password',
            'user_type' => 'admin', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    /** Un ordine di 3 pecorini e 2 magnum, come uscito dalla cassa. */
    private function order(string $status = 'paid', array $site = ['site' => 'domain'], bool $deducted = true): Order
    {
        $order = Order::create([
            'user_id' => $this->buyer->id, 'company_id' => $this->company->id, 'subtotal' => 90, 'total' => 90,
            'currency' => 'EUR', 'status' => $status, 'billing_name' => 'Anna Bianchi', 'billing_email' => 'anna@example.test',
        ] + $site + ($site['site'] === 'domain' ? ['domain_id' => $this->domain->id] : []));

        OrderItem::create(['order_id' => $order->id, 'product_id' => $this->cheese->id, 'product_name' => 'Pecorino', 'product_price' => 10, 'quantity' => 3, 'subtotal' => 30]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $this->wine->id, 'product_variant_id' => $this->magnum->id, 'product_name' => 'Vino — Magnum', 'product_price' => 30, 'quantity' => 2, 'subtotal' => 60]);

        if ($deducted) {
            // Come dopo il pagamento: merce gia' scalata.
            $this->cheese->update(['stock' => 17]);
            $this->magnum->update(['variant_stock' => 4]);
            $order->forceFill(['stock_deducted_at' => now()])->save();
        }

        return $order;
    }

    private function stocks(): array
    {
        return [(int) $this->cheese->fresh()->stock, (int) $this->magnum->fresh()->variant_stock];
    }

    /** @return list<\Symfony\Component\Mime\Email> */
    private function sentMails(): array
    {
        return collect(app('mailer')->getSymfonyTransport()->messages())->map(fn ($sent) => $sent->getOriginalMessage())->all();
    }

    private function changeStatus(User $user, Order $order, array $data, string $route = 'admin.orders.status')
    {
        return $this->actingAs($user)->patch(self::MAIN.route($route, $order, false), $data + ['notify' => 1]);
    }

    // Disponibilita'

    public function test_annullare_restituisce_la_merce_e_riattivare_la_riprende_una_volta_sola(): void
    {
        $admin = $this->admin();
        $order = $this->order();
        $this->assertSame([17, 4], $this->stocks());

        $this->changeStatus($admin, $order, ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertSame([20, 6], $this->stocks());
        $this->assertNull($order->fresh()->stock_deducted_at);

        // Di nuovo annullato: niente di doppio.
        $this->changeStatus($admin, $order, ['status' => 'cancelled']);
        $this->assertSame([20, 6], $this->stocks());

        $this->changeStatus($admin, $order, ['status' => 'paid']);
        $this->assertSame([17, 4], $this->stocks());

        $this->changeStatus($admin, $order, ['status' => 'shipped']);
        $this->assertSame([17, 4], $this->stocks());

        // In attesa vuol dire non pagato: la merce torna libera.
        $this->changeStatus($admin, $order, ['status' => 'pending']);
        $this->assertSame([20, 6], $this->stocks());
    }

    public function test_il_pagamento_segna_la_merce_scalata(): void
    {
        $order = $this->order('pending', deducted: false);
        $payment = Payment::create(['user_id' => $this->buyer->id, 'company_id' => $this->company->id, 'method' => 'stripe', 'mode' => 'test', 'amount' => 90, 'currency' => 'EUR', 'status' => 'pending']);
        $order->update(['payment_id' => $payment->id]);

        app(PaymentCompletion::class)->markPaid($order, $payment, 'pi_1', []);

        $this->assertSame([17, 4], $this->stocks());
        $this->assertNotNull($order->fresh()->stock_deducted_at);

        // Annullato dopo il pagamento online: la merce torna.
        $this->changeStatus($this->admin(), $order, ['status' => 'cancelled']);
        $this->assertSame([20, 6], $this->stocks());
    }

    public function test_in_blocco_ogni_ordine_restituisce_la_sua_merce(): void
    {
        $first = $this->order();
        $second = Order::create([
            'user_id' => $this->buyer->id, 'company_id' => $this->company->id, 'subtotal' => 10, 'total' => 10, 'currency' => 'EUR',
            'status' => 'paid', 'billing_email' => 'anna@example.test', 'site' => 'platform',
        ]);
        $second->forceFill(['stock_deducted_at' => now()])->save();
        OrderItem::create(['order_id' => $second->id, 'product_id' => $this->cheese->id, 'product_name' => 'Pecorino', 'product_price' => 10, 'quantity' => 1, 'subtotal' => 10]);
        $this->cheese->update(['stock' => 16]);

        $this->actingAs($this->admin())->patch(self::MAIN.route('admin.orders.bulk', [], false), [
            'action' => 'status', 'status' => 'cancelled', 'scope' => 'selected', 'ids' => [$first->id, $second->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame([20, 6], $this->stocks());
        $this->assertSame(['cancelled', 'cancelled'], [$first->fresh()->status, $second->fresh()->status]);
    }

    // Email al cliente

    public function test_spedito_avvisa_il_cliente_con_il_nome_e_l_indirizzo_del_sito(): void
    {
        $order = $this->order();

        $this->changeStatus($this->admin(), $order, [
            'status' => 'shipped', 'carrier' => 'BRT', 'tracking_number' => 'ABC123', 'tracking_url' => 'https://brt.test/ABC123',
        ])->assertSessionHas('success');

        $mails = $this->sentMails();
        $this->assertCount(1, $mails);
        $mail = $mails[0];
        $html = $mail->getHtmlBody();

        $this->assertSame('anna@example.test', $mail->getTo()[0]->getAddress());
        $this->assertSame('Sapori di Calabria', $mail->getFrom()[0]->getName());
        $this->assertStringContainsString('ORD-', $mail->getSubject());
        $this->assertStringContainsString('Sapori di Calabria', $mail->getSubject());
        $this->assertSame('info@caseificio.test', $mail->getReplyTo()[0]->getAddress());
        $this->assertStringContainsString('BRT', $html);
        $this->assertStringContainsString('ABC123', $html);
        $this->assertStringContainsString('https://brt.test/ABC123', $html);
        $this->assertStringContainsString('https://saporicalabria.test/account/ordini/'.$order->id, $html);
        $this->assertStringNotContainsString('KSM', $html);

        $this->assertNotNull($order->fresh()->shipped_at);
        $this->assertSame('BRT', $order->fresh()->carrier);
    }

    public function test_annullato_avvisa_senza_la_casella_no(): void
    {
        $admin = $this->admin();
        $order = $this->order();

        $this->changeStatus($admin, $order, ['status' => 'cancelled']);
        $this->assertCount(1, $this->sentMails());
        $this->assertStringContainsString('annullato', $this->sentMails()[0]->getSubject());

        // Senza la casella, e per stati che non la prevedono, nessuna email.
        $this->actingAs($admin)->patch(self::MAIN.route('admin.orders.status', $order, false), ['status' => 'paid']);
        $this->actingAs($admin)->patch(self::MAIN.route('admin.orders.status', $order, false), ['status' => 'shipped']);
        $this->changeStatus($admin, $order, ['status' => 'completed']);
        $this->assertCount(1, $this->sentMails());
    }

    public function test_l_azienda_cambia_stato_e_spedizione_con_le_stesse_regole(): void
    {
        $order = $this->order('paid', ['site' => 'platform']);
        $owner = $this->company->user;

        $this->actingAs($owner)->get(self::MAIN.route('vendor.orders.show', $order, false))
            ->assertOk()->assertSee('name="tracking_number"', false)->assertDontSee('name="admin_notes"', false);

        $this->changeStatus($owner, $order, ['status' => 'cancelled'], 'vendor.orders.status')->assertSessionHasNoErrors();

        $this->assertSame([20, 6], $this->stocks());
        $this->assertCount(1, $this->sentMails());
        // Sito principale: nome e indirizzo di KSM.
        $this->assertSame(config('ksm.brand_name'), $this->sentMails()[0]->getFrom()[0]->getName());
        $this->assertStringContainsString('KSM-', $this->sentMails()[0]->getSubject());
    }

    public function test_il_cliente_vede_la_spedizione(): void
    {
        $order = $this->order();
        $order->update(['status' => 'shipped', 'carrier' => 'Poste', 'tracking_number' => 'PX99', 'tracking_url' => 'https://poste.test/PX99', 'shipped_at' => now()]);

        $this->actingAs($this->buyer)->get('http://saporicalabria.test/account/ordini/'.$order->id)
            ->assertOk()->assertSee('Poste')->assertSee('PX99')->assertSee('https://poste.test/PX99', false)
            ->assertDontSee('· KSM');

        auth()->logout();
        $this->post('http://saporicalabria.test/traccia-ordine', ['reference' => $order->reference, 'email' => 'anna@example.test'])
            ->assertOk()->assertSee('PX99');
    }

    // Modifica e inserimento

    public function test_modificare_le_righe_ricalcola_totali_e_disponibilita(): void
    {
        $order = $this->order();
        [$cheeseRow, $wineRow] = $order->items()->orderBy('id')->get()->all();

        $this->actingAs($this->admin())->get(self::MAIN.route('admin.orders.edit', $order, false))->assertOk()->assertSee('Pecorino');

        $this->actingAs($this->admin())->put(self::MAIN.route('admin.orders.update', $order, false), [
            'billing_name' => 'Anna Bianchi', 'billing_email' => 'anna@example.test', 'billing_city' => 'Reggio Calabria',
            'shipping' => 5,
            'items' => [
                ['id' => $cheeseRow->id, 'quantity' => 5, 'price' => 9],
                ['id' => $wineRow->id, 'quantity' => 2, 'price' => 30, 'remove' => 1],
                ['product' => 'p:'.$this->cheese->id, 'quantity' => 1, 'price' => ''],
                ['product' => '', 'quantity' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(2, $order->items()->count());
        $this->assertEquals(55.0, (float) $order->subtotal);   // 5 x 9 + 1 x 10
        $this->assertEquals(60.0, (float) $order->total);
        $this->assertSame('Reggio Calabria', $order->billing_city);
        // 6 pecorini impegnati, i magnum tornano tutti.
        $this->assertSame([14, 6], $this->stocks());
    }

    public function test_un_prodotto_di_un_altra_azienda_non_entra_nell_ordine(): void
    {
        $other = Company::create(['user_id' => $this->buyer->id, 'name' => 'Altra', 'slug' => 'altra', 'is_active' => true]);
        $foreign = Product::create(['company_id' => $other->id, 'name' => 'Estraneo', 'slug' => 'estraneo', 'price' => 1, 'status' => 'active']);
        $order = $this->order();

        $this->actingAs($this->admin())->put(self::MAIN.route('admin.orders.update', $order, false), [
            'billing_name' => 'Anna', 'billing_email' => 'anna@example.test',
            'items' => [['product' => 'p:'.$foreign->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items.0.product');

        $this->assertSame(2, $order->items()->count());
    }

    public function test_un_ordine_a_mano_prende_merce_account_e_sito(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(self::MAIN.route('admin.orders.create', ['cerca' => 'Caseificio'], false))
            ->assertOk()->assertSee('azienda='.$this->company->id, false);
        $this->actingAs($admin)->get(self::MAIN.route('admin.orders.create', ['azienda' => $this->company->id], false))
            ->assertOk()->assertSee('Vino — '.$this->magnum->label())->assertSee('saporicalabria.test');

        $this->actingAs($admin)->post(self::MAIN.route('admin.orders.store', [], false), [
            'company_id' => $this->company->id, 'site' => 'domain:'.$this->domain->id, 'status' => 'paid',
            'billing_name' => 'Anna Bianchi', 'billing_email' => 'anna@example.test', 'shipping' => 4,
            'items' => [
                ['product' => 'v:'.$this->wine->id.':'.$this->magnum->id, 'quantity' => 1, 'price' => ''],
                ['product' => 'p:'.$this->cheese->id, 'quantity' => 2, 'price' => 8.5],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $order = Order::latest('id')->firstOrFail();
        $this->assertSame($this->buyer->id, $order->user_id);
        $this->assertSame(['domain', $this->domain->id], [$order->site, $order->domain_id]);
        $this->assertEquals(51.0, (float) $order->total);       // 30 + 2 x 8,50 + 4
        $this->assertSame([18, 5], $this->stocks());
        $this->assertStringStartsWith('ORD-', $order->reference);
        $this->assertCount(0, $this->sentMails());

        // Il cliente lo ritrova sul sito scelto.
        $this->actingAs($this->buyer)->get('http://saporicalabria.test/account/ordini/'.$order->id)->assertOk();
    }

    public function test_un_ordine_a_mano_per_chi_non_ha_account(): void
    {
        $this->actingAs($this->admin())->post(self::MAIN.route('admin.orders.store', [], false), [
            'company_id' => $this->company->id, 'site' => 'platform', 'status' => 'pending',
            'billing_name' => 'Mario Verdi', 'billing_email' => 'mario@example.test',
            'items' => [['product' => 'p:'.$this->cheese->id, 'quantity' => 1]],
        ])->assertSessionHasNoErrors();

        $order = Order::latest('id')->firstOrFail();
        $this->assertNull($order->user_id);
        // In attesa: la merce non si tocca.
        $this->assertSame([20, 6], $this->stocks());
        $this->actingAs($this->admin())->get(self::MAIN.route('admin.orders.show', $order, false))->assertOk()->assertSee('nessuno');
    }

    // Filtri ed esportazione

    public function test_filtri_per_azienda_sito_e_date_ed_esportazione(): void
    {
        $admin = $this->admin();
        $onDomain = $this->order();
        $onMain = $this->order('paid', ['site' => 'platform'], deducted: false);
        $old = $this->order('completed', ['site' => 'platform'], deducted: false);
        $old->forceFill(['created_at' => now()->subYear()])->save();

        $list = fn (array $query) => $this->actingAs($admin)->get(self::MAIN.route('admin.orders.index', $query, false))->assertOk();

        $list(['sito' => 'domain:'.$this->domain->id])->assertSee($onDomain->reference)->assertDontSee($onMain->reference);
        $list(['sito' => 'platform', 'dal' => now()->subMonth()->format('Y-m-d')])->assertSee($onMain->reference)->assertDontSee($old->reference);
        $list(['azienda_nome' => 'Caseificio'])->assertSee($onMain->reference);
        $list(['azienda_nome' => 'Nessuna'])->assertDontSee($onMain->reference);

        $csv = $this->actingAs($admin)->get(self::MAIN.route('admin.orders.export', ['sito' => 'platform'], false))
            ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF".'Numero;Data;Stato', $csv);
        $this->assertStringContainsString($onMain->reference, $csv);
        $this->assertStringContainsString($old->reference, $csv);
        $this->assertStringNotContainsString($onDomain->reference, $csv);
        $this->assertStringContainsString('3 x Pecorino | 2 x Vino — Magnum', $csv);
        $this->assertStringContainsString(';90,00;', $csv);
    }

    public function test_chi_vede_gli_ordini_esporta_ma_non_modifica(): void
    {
        $viewer = $this->admin([Permissions::ORDERS_VIEW]);
        $order = $this->order();

        $this->actingAs($viewer)->get(self::MAIN.route('admin.orders.index', [], false))->assertOk()->assertDontSee('Nuovo ordine');
        $this->actingAs($viewer)->get(self::MAIN.route('admin.orders.export', [], false))->assertOk();
        $this->actingAs($viewer)->get(self::MAIN.route('admin.orders.show', $order, false))->assertOk()->assertDontSee('Modifica ordine');
        $this->actingAs($viewer)->get(self::MAIN.route('admin.orders.edit', $order, false))->assertForbidden();
        $this->actingAs($viewer)->get(self::MAIN.route('admin.orders.create', [], false))->assertForbidden();
        $this->changeStatus($viewer, $order, ['status' => 'cancelled'])->assertForbidden();
    }
}
