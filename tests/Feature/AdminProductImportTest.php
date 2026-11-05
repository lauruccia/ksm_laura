<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Import da CSV WooCommerce fatto dall'amministrazione: si sceglie l'azienda di destinazione.
 */
class AdminProductImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $role = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super amministratore', 'is_system' => true]);
        $this->admin = User::create([
            'name' => 'Amministratore', 'email' => 'admin@example.test', 'password' => 'password',
            'user_type' => 'admin', 'role_id' => $role->id, 'is_active' => true,
        ]);

        $plan = Plan::create([
            'name' => 'Ecommerce', 'slug' => 'ecommerce', 'price' => 349, 'priority' => 40, 'duration_days' => 365,
            'is_active' => true, 'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOP],
        ]);
        $owner = User::create(['name' => 'Venditore', 'email' => 'venditore@example.test', 'password' => 'password', 'user_type' => 'vendor']);
        $this->company = Company::create([
            'user_id' => $owner->id, 'plan_id' => $plan->id, 'name' => 'Calabria Sapori', 'slug' => 'calabria-sapori', 'is_active' => true,
        ]);
    }

    private function file(string $sku = 'KM-A1', string $price = '10.00'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'prodotti.csv',
            "Type,SKU,Name,Published,\"Regular price\",\"Meta: _kmoney_ky_percentage\"\nsimple,{$sku},Nduja,1,{$price},100\n"
        );
    }

    public function test_senza_azienda_la_pagina_chiede_di_cercarla(): void
    {
        $this->actingAs($this->admin)->get(route('admin.products.import'))
            ->assertOk()->assertSee('Nome dell');

        $this->get(route('admin.products.import', ['cerca' => 'Calabria']))
            ->assertOk()->assertSee('Calabria Sapori')->assertSee('Importa qui');
    }

    public function test_con_l_azienda_scelta_si_vede_il_modulo_di_caricamento(): void
    {
        $this->actingAs($this->admin)->get(route('admin.products.import', ['azienda' => $this->company->id]))
            ->assertOk()->assertSee('Calabria Sapori')->assertSee('Controlla il file');
    }

    public function test_il_pulsante_importa_csv_c_e_nell_elenco_prodotti(): void
    {
        $this->actingAs($this->admin)->get(route('admin.products.index'))
            ->assertOk()->assertSee('Importa CSV');
    }

    public function test_l_anteprima_non_scrive_e_la_conferma_importa_nell_azienda_scelta(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.products.import.analyze'), ['azienda' => $this->company->id, 'csv' => $this->file()])
            ->assertOk();

        $this->assertSame(0, Product::count());

        $this->post(route('admin.products.import.apply'), [
            'azienda' => $this->company->id, 'token' => $response->viewData('token'),
        ])->assertRedirect(route('admin.products.index', ['azienda' => $this->company->id]));

        $p = Product::where('sku', 'KM-A1')->firstOrFail();
        $this->assertSame($this->company->id, $p->company_id);
        $this->assertEquals(100, (int) $p->kmoney_discount_percent);
    }

    public function test_senza_azienda_l_analisi_e_rifiutata(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.products.import.analyze'), ['csv' => $this->file()])
            ->assertSessionHasErrors('azienda');

        $this->assertSame(0, Product::count());
    }

    public function test_chi_non_e_amministratore_non_apre_la_pagina(): void
    {
        $vendor = $this->company->user;

        $status = $this->actingAs($vendor)->get(route('admin.products.import'))->getStatusCode();

        $this->assertNotSame(200, $status);
    }
}
