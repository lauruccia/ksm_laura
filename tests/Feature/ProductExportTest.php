<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Export dei prodotti in CSV formato WooCommerce (stesso formato dell'import e di kmoney).
 */
class ProductExportTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    private Company $company;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->plan = Plan::create([
            'name' => 'Ecommerce', 'slug' => 'ecommerce', 'price' => 349, 'priority' => 40, 'duration_days' => 365,
            'is_active' => true, 'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOP],
        ]);
        [$this->vendor, $this->company] = $this->makeCompany('venditore@example.test', 'Calabria Sapori', 'calabria-sapori');
    }

    /** @return array{0: User, 1: Company} */
    private function makeCompany(string $email, string $name, string $slug): array
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => 'password', 'user_type' => 'vendor']);
        $company = Company::create([
            'user_id' => $user->id, 'plan_id' => $this->plan->id, 'name' => $name, 'slug' => $slug, 'is_active' => true,
        ]);

        return [$user, $company];
    }

    private function product(Company $company, array $attrs): Product
    {
        return Product::create($attrs + [
            'company_id' => $company->id, 'slug' => 'p-'.uniqid(), 'product_type' => 'simple', 'status' => 'active', 'price' => 10,
        ]);
    }

    /** @return list<array<string,string>> le righe del CSV, con le intestazioni come chiavi */
    private function parse(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $h = fopen('php://memory', 'r+');
        fwrite($h, $csv);
        rewind($h);

        $header = fgetcsv($h, 0, ',', '"', '');
        $rows = [];
        while (($cells = fgetcsv($h, 0, ',', '"', '')) !== false) {
            $rows[] = array_combine($header, $cells);
        }
        fclose($h);

        return $rows;
    }

    private function seedCatalog(): void
    {
        $top = ProductCategory::create(['name' => 'Mangiare e Bere', 'slug' => 'mangiare-e-bere']);
        $child = ProductCategory::create(['name' => 'Salumi', 'slug' => 'salumi', 'parent_id' => $top->id]);

        $this->product($this->company, [
            'name' => 'Nduja', 'sku' => 'KM-1', 'price' => 10, 'discount_price' => 8, 'stock' => 5,
            'kmoney_discount_percent' => 100, 'category_id' => $child->id, 'weight_kg' => 0.5,
        ]);

        $variable = $this->product($this->company, ['name' => 'Maglia', 'sku' => 'KM-V', 'product_type' => 'variable', 'price' => 20]);
        foreach ([['S / Rosso', 20, 'KM-V-1'], ['M / Rosso', 22, 'KM-V-2'], ['M / Blu', 22, 'KM-V-3']] as [$value, $price, $sku]) {
            ProductVariant::create([
                'product_id' => $variable->id, 'variant_type' => 'Taglia / Colore', 'variant_value' => $value,
                'variant_price' => $price, 'variant_stock' => null, 'variant_sku' => $sku,
            ]);
        }
    }

    public function test_il_venditore_esporta_prodotti_e_varianti_in_formato_woocommerce(): void
    {
        $this->seedCatalog();

        $response = $this->actingAs($this->vendor)->get(route('vendor.products.export'))->assertOk();
        $this->assertStringContainsString('prodotti-calabria-sapori-', $response->headers->get('content-disposition'));

        $rows = collect($this->parse($response->streamedContent()));
        $this->assertCount(5, $rows); // 2 prodotti + 3 variazioni

        $simple = $rows->firstWhere('SKU', 'KM-1');
        $this->assertSame('simple', $simple['Type']);
        $this->assertSame('', $simple['ID']);
        $this->assertSame('10.00', $simple['Regular price']);
        $this->assertSame('8.00', $simple['Sale price']);
        $this->assertSame('5', $simple['Stock']);
        $this->assertSame('Mangiare e Bere > Salumi', $simple['Categories']);
        $this->assertSame('100', $simple['Meta: _kmoney_ky_percentage']);

        $variable = $rows->firstWhere('SKU', 'KM-V');
        $this->assertSame('variable', $variable['Type']);
        $this->assertSame('', $variable['Regular price']);
        $this->assertSame('Taglia', $variable['Attribute 1 name']);
        $this->assertSame('S, M', $variable['Attribute 1 value(s)']);
        $this->assertSame('Colore', $variable['Attribute 2 name']);
        $this->assertSame('Rosso, Blu', $variable['Attribute 2 value(s)']);

        $variation = $rows->firstWhere('SKU', 'KM-V-2');
        $this->assertSame('variation', $variation['Type']);
        $this->assertSame('KM-V', $variation['Parent']);
        $this->assertSame('22.00', $variation['Regular price']);
        $this->assertSame('M', $variation['Attribute 1 value(s)']);
        $this->assertSame('Rosso', $variation['Attribute 2 value(s)']);
    }

    public function test_si_esportano_solo_i_prodotti_della_propria_azienda(): void
    {
        $this->seedCatalog();
        [, $other] = $this->makeCompany('altro@example.test', 'Altra', 'altra');
        $this->product($other, ['name' => 'Altrui', 'sku' => 'ALTRO-1']);

        $csv = $this->actingAs($this->vendor)->get(route('vendor.products.export'))->streamedContent();

        $this->assertStringContainsString('KM-1', $csv);
        $this->assertStringNotContainsString('ALTRO-1', $csv);
    }

    public function test_il_filtro_di_stato_vale_anche_per_l_export(): void
    {
        $this->seedCatalog();
        $this->product($this->company, ['name' => 'Spento', 'sku' => 'OFF-1', 'status' => 'inactive']);

        $csv = $this->actingAs($this->vendor)->get(route('vendor.products.export', ['stato' => 'inactive']))->streamedContent();

        $this->assertStringContainsString('OFF-1', $csv);
        $this->assertStringNotContainsString('KM-1', $csv);
    }

    public function test_un_prodotto_variabile_senza_sku_si_lega_alle_variazioni_con_l_id(): void
    {
        $p = $this->product($this->company, ['name' => 'Senza sku', 'product_type' => 'variable']);
        ProductVariant::create([
            'product_id' => $p->id, 'variant_type' => 'Formato', 'variant_value' => '1kg', 'variant_price' => 9, 'variant_sku' => null,
        ]);

        $rows = $this->parse($this->actingAs($this->vendor)->get(route('vendor.products.export'))->streamedContent());

        $this->assertSame((string) $p->id, $rows[0]['ID']);
        $this->assertSame('id:'.$p->id, $rows[1]['Parent']);
    }

    public function test_il_file_esportato_si_reimporta_in_un_altra_azienda_con_le_stesse_varianti(): void
    {
        $this->seedCatalog();
        $csv = $this->actingAs($this->vendor)->get(route('vendor.products.export'))->streamedContent();

        [$other, $company] = $this->makeCompany('altro@example.test', 'Altra', 'altra');
        $file = UploadedFile::fake()->createWithContent('prodotti.csv', $csv);

        $response = $this->actingAs($other)->post(route('vendor.products.import.analyze'), ['csv' => $file])->assertOk();
        $this->post(route('vendor.products.import.apply'), ['token' => $response->viewData('token')]);

        $copy = Product::where('company_id', $company->id)->where('sku', 'KM-V')->firstOrFail();
        $this->assertSame('variable', $copy->product_type);
        $this->assertEqualsCanonicalizing(
            ['S / Rosso', 'M / Rosso', 'M / Blu'],
            $copy->variants()->pluck('variant_value')->all()
        );
        $this->assertSame('Taglia / Colore', $copy->variants()->first()->variant_type);

        $nduja = Product::where('company_id', $company->id)->where('sku', 'KM-1')->firstOrFail();
        $this->assertEquals(10.0, (float) $nduja->price);
        $this->assertEquals(8.0, (float) $nduja->discount_price);
        $this->assertNotNull($nduja->category_id);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super amministratore', 'is_system' => true]);

        return User::create([
            'name' => 'Amministratore', 'email' => 'admin@example.test', 'password' => 'password',
            'user_type' => 'admin', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    public function test_l_admin_vede_esporta_anche_senza_scegliere_un_azienda(): void
    {
        $this->seedCatalog();

        $this->actingAs($this->admin())->get(route('admin.products.index'))
            ->assertOk()->assertSee('Esporta CSV');
    }

    public function test_l_admin_esporta_i_prodotti_filtrati_di_tutte_le_aziende_con_la_colonna_azienda(): void
    {
        $this->seedCatalog();
        [, $other] = $this->makeCompany('altro@example.test', 'Altra Srl', 'altra');
        $this->product($other, ['name' => 'Altrui', 'sku' => 'ALTRO-1']);

        $csv = $this->actingAs($this->admin())->get(route('admin.products.export'))->streamedContent();
        $rows = collect($this->parse($csv));

        $this->assertSame('Altra Srl', $rows->firstWhere('SKU', 'ALTRO-1')['Azienda']);
        $this->assertSame('Calabria Sapori', $rows->firstWhere('SKU', 'KM-1')['Azienda']);
        $this->assertSame('Calabria Sapori', $rows->firstWhere('SKU', 'KM-V-2')['Azienda']);
    }

    public function test_l_admin_filtra_per_nome_azienda_e_per_nome_prodotto(): void
    {
        $this->seedCatalog();
        [, $other] = $this->makeCompany('altro@example.test', 'Altra Srl', 'altra');
        $this->product($other, ['name' => 'Altrui', 'sku' => 'ALTRO-1']);
        $admin = $this->admin();

        $byCompany = $this->actingAs($admin)->get(route('admin.products.export', ['nome_azienda' => 'Altra']))->streamedContent();
        $this->assertStringContainsString('ALTRO-1', $byCompany);
        $this->assertStringNotContainsString('KM-1', $byCompany);

        $byName = $this->get(route('admin.products.export', ['cerca' => 'Nduja']))->streamedContent();
        $this->assertStringContainsString('KM-1', $byName);
        $this->assertStringNotContainsString('ALTRO-1', $byName);
    }

    public function test_con_un_azienda_sola_la_colonna_azienda_non_c_e(): void
    {
        $this->seedCatalog();

        $csv = $this->actingAs($this->admin())->get(route('admin.products.export', ['azienda' => $this->company->id]))->streamedContent();

        $this->assertArrayNotHasKey('Azienda', $this->parse($csv)[0]);
    }
}
