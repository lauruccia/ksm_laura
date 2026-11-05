<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Import dei prodotti da CSV in formato WooCommerce (o export di kmoney), area azienda.
 * Due tempi: anteprima che non scrive niente, poi conferma.
 */
class VendorProductImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = [
        'Type', 'SKU', 'Name', 'Published', 'In stock?', 'Stock', 'Regular price', 'Categories', 'Images',
        'Parent', 'Attribute 1 name', 'Attribute 1 value(s)', 'Meta: _kmoney_ky_percentage',
    ];

    private User $vendor;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $plan = Plan::create([
            'name' => 'Ecommerce', 'slug' => 'ecommerce', 'price' => 349, 'priority' => 40, 'duration_days' => 365,
            'is_active' => true, 'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOP],
        ]);
        $this->vendor = User::create(['name' => 'Venditore', 'email' => 'venditore@example.test', 'password' => 'password', 'user_type' => 'vendor']);
        $this->company = Company::create([
            'user_id' => $this->vendor->id, 'plan_id' => $plan->id, 'name' => 'Calabria Sapori', 'slug' => 'calabria-sapori', 'is_active' => true,
        ]);
    }

    /** @param list<array<string,string>> $rows  chiavi = intestazioni di HEADER */
    private function file(array $rows, ?array $header = null): UploadedFile
    {
        $header ??= self::HEADER;
        $h = fopen('php://memory', 'r+');
        fputcsv($h, $header, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($h, array_map(fn ($col) => $row[$col] ?? '', $header), ',', '"', '');
        }
        rewind($h);
        $content = stream_get_contents($h);
        fclose($h);

        return UploadedFile::fake()->createWithContent('prodotti.csv', $content);
    }

    private function simple(string $sku, string $name, string $price, array $extra = []): array
    {
        return $extra + ['Type' => 'simple', 'SKU' => $sku, 'Name' => $name, 'Published' => '1', 'Regular price' => $price];
    }

    private function analyze(UploadedFile $file, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->vendor)
            ->post(route('vendor.products.import.analyze'), ['csv' => $file]);
    }

    /** Anteprima + conferma, come fa l'utente. */
    private function importFile(UploadedFile $file, ?User $as = null): void
    {
        $response = $this->analyze($file, $as)->assertOk();
        $token = $response->viewData('token');

        $this->post(route('vendor.products.import.apply'), ['token' => $token])
            ->assertRedirect(route('vendor.products.index'));
    }

    public function test_crea_un_prodotto_con_la_quota_kmoney_del_file(): void
    {
        $this->importFile($this->file([$this->simple('KM-1', 'Nduja', '10.00', ['Meta: _kmoney_ky_percentage' => '100'])]));

        $p = Product::where('company_id', $this->company->id)->where('sku', 'KM-1')->firstOrFail();
        $this->assertSame('Nduja', $p->name);
        $this->assertEquals(10.0, (float) $p->price);
        $this->assertEquals(100, (int) $p->kmoney_discount_percent);
        $this->assertSame('simple', $p->product_type);
        $this->assertSame('active', $p->status);
    }

    public function test_senza_quota_nel_file_resta_automatica(): void
    {
        $this->importFile($this->file([$this->simple('KM-2', 'Capicollo', '22.00')]));

        $this->assertNull(Product::where('sku', 'KM-2')->firstOrFail()->kmoney_discount_percent);
    }

    public function test_giacenza_vuota_vuol_dire_non_gestita(): void
    {
        $this->importFile($this->file([
            $this->simple('KM-3', 'Passata', '2.50', ['Stock' => '']),
            $this->simple('KM-4', 'Olive', '6.50', ['Stock' => '12']),
        ]));

        $this->assertNull(Product::where('sku', 'KM-3')->firstOrFail()->stock);
        $this->assertSame(12, Product::where('sku', 'KM-4')->firstOrFail()->stock);
    }

    public function test_riconosce_intestazioni_italiane_e_prezzi_con_la_virgola(): void
    {
        $header = ['Tipo', 'SKU', 'Nome', 'Pubblicato', 'Prezzo di listino'];
        $rows = [['Tipo' => 'simple', 'SKU' => 'IT-1', 'Nome' => 'Marmellata', 'Pubblicato' => '1', 'Prezzo di listino' => '4,50']];

        $this->importFile($this->file($rows, $header));

        $this->assertEquals(4.5, (float) Product::where('sku', 'IT-1')->firstOrFail()->price);
    }

    public function test_reimportare_aggiorna_per_sku_e_mostra_le_differenze(): void
    {
        $this->importFile($this->file([$this->simple('KM-5', 'Salsiccia', '8.00')]));

        $response = $this->analyze($this->file([$this->simple('KM-5', 'Salsiccia', '9.50')]))->assertOk();
        $item = $response->viewData('plan')['items'][0];
        $this->assertSame('update', $item['action']);
        $this->assertStringContainsString('Prezzo', implode(' ', $item['diff']));

        $this->post(route('vendor.products.import.apply'), ['token' => $response->viewData('token')]);

        $this->assertSame(1, Product::where('company_id', $this->company->id)->where('sku', 'KM-5')->count());
        $this->assertEquals(9.5, (float) Product::where('sku', 'KM-5')->firstOrFail()->price);
    }

    public function test_un_file_identico_non_cambia_niente(): void
    {
        $this->importFile($this->file([$this->simple('KM-6', 'Pancetta', '12.00')]));

        $plan = $this->analyze($this->file([$this->simple('KM-6', 'Pancetta', '12.00')]))->assertOk()->viewData('plan');

        $this->assertSame(1, $plan['counts']['same']);
        $this->assertSame(0, $plan['counts']['create'] + $plan['counts']['update']);
    }

    public function test_lo_stesso_sku_di_un_altra_azienda_non_si_tocca(): void
    {
        $other = User::create(['name' => 'Altro', 'email' => 'altro@example.test', 'password' => 'password', 'user_type' => 'vendor']);
        $otherCompany = Company::create([
            'user_id' => $other->id, 'plan_id' => $this->company->plan_id, 'name' => 'Altra', 'slug' => 'altra', 'is_active' => true,
        ]);
        $theirs = Product::create([
            'company_id' => $otherCompany->id, 'name' => 'Loro', 'slug' => 'loro', 'sku' => 'KM-7',
            'price' => 3, 'product_type' => 'simple', 'status' => 'active',
        ]);

        $this->importFile($this->file([$this->simple('KM-7', 'Nostro', '20.00')]));

        $this->assertEquals(3.0, (float) $theirs->fresh()->price);
        $this->assertSame('Loro', $theirs->fresh()->name);
        $this->assertSame(1, Product::where('company_id', $this->company->id)->where('sku', 'KM-7')->count());
    }

    public function test_un_prodotto_variabile_crea_le_varianti_col_prezzo_pieno(): void
    {
        $rows = [
            ['Type' => 'variable', 'SKU' => 'KM-V', 'Name' => 'Melanzane', 'Published' => '1',
                'Attribute 1 name' => 'Formato', 'Attribute 1 value(s)' => '280gr, 3100ml', 'Meta: _kmoney_ky_percentage' => '100'],
            ['Type' => 'variation', 'SKU' => 'KM-V-1', 'Name' => 'Melanzane', 'Published' => '1', 'Regular price' => '6.97',
                'Parent' => 'KM-V', 'Attribute 1 name' => 'Formato', 'Attribute 1 value(s)' => '280gr'],
            ['Type' => 'variation', 'SKU' => 'KM-V-2', 'Name' => 'Melanzane', 'Published' => '1', 'Regular price' => '28.00',
                'Parent' => 'KM-V', 'Attribute 1 name' => 'Formato', 'Attribute 1 value(s)' => '3100ml'],
        ];

        $this->importFile($this->file($rows));

        $p = Product::where('sku', 'KM-V')->firstOrFail();
        $this->assertSame('variable', $p->product_type);
        $this->assertSame(2, $p->variants()->count());
        $this->assertEquals([6.97, 28.0], $p->variants()->pluck('variant_price')->map(fn ($v) => (float) $v)->sort()->values()->all());
        $this->assertEquals(6.97, (float) $p->price);
    }

    public function test_una_riga_senza_prezzo_non_ferma_le_altre(): void
    {
        $response = $this->analyze($this->file([
            $this->simple('OK-1', 'Buono', '5.00'),
            $this->simple('KO-1', 'Senza prezzo', ''),
        ]))->assertOk();

        $this->post(route('vendor.products.import.apply'), ['token' => $response->viewData('token')]);

        $this->assertNotNull(Product::where('sku', 'OK-1')->first());
        $this->assertNull(Product::where('sku', 'KO-1')->first());
    }

    public function test_un_file_che_non_e_woocommerce_viene_rifiutato(): void
    {
        $file = UploadedFile::fake()->createWithContent('altro.csv', "colonna1,colonna2\na,b\n");

        $this->analyze($file)->assertSessionHasErrors('csv');
        $this->assertSame(0, Product::where('company_id', $this->company->id)->count());
    }

    public function test_l_anteprima_non_scrive_niente_e_la_conferma_importa(): void
    {
        $response = $this->analyze($this->file([$this->simple('KM-8', 'Soppressata', '15.00')]))->assertOk();

        $this->assertSame(0, Product::where('company_id', $this->company->id)->count());

        $this->post(route('vendor.products.import.apply'), ['token' => $response->viewData('token')]);

        $this->assertSame(1, Product::where('company_id', $this->company->id)->count());
    }

    public function test_il_token_di_un_altro_utente_non_funziona(): void
    {
        $token = $this->analyze($this->file([$this->simple('KM-9', 'Guanciale', '9.00')]))->assertOk()->viewData('token');

        $other = User::create(['name' => 'Altro', 'email' => 'altro2@example.test', 'password' => 'password', 'user_type' => 'vendor']);
        Company::create([
            'user_id' => $other->id, 'plan_id' => $this->company->plan_id, 'name' => 'Altra 2', 'slug' => 'altra-2', 'is_active' => true,
        ]);

        $this->actingAs($other)->post(route('vendor.products.import.apply'), ['token' => $token])
            ->assertSessionHasErrors('csv');

        $this->assertNull(Product::where('sku', 'KM-9')->first());
    }
}
