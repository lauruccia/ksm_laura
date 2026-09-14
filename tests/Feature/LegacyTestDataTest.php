<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\Legacy\LegacyTestData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Aziende, prodotti e marche inventati per provare il vecchio sito.
 */
class LegacyTestDataTest extends TestCase
{
    use RefreshDatabase;

    private function company(int $id, string $name): Company
    {
        $user = User::create([
            'name' => $name,
            'email' => Str::slug($name).'@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        return Company::forceCreate([
            'id' => $id,
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ]);
    }

    private function product(Company $company, string $name, ?int $brandId = null): int
    {
        return DB::table('products')->insertGetId([
            'company_id' => $company->id,
            'brand_id' => $brandId,
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'price' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_toglie_i_dati_di_prova_e_lascia_quelli_veri(): void
    {
        $fakeBrand = DB::table('product_brands')->insertGetId(['name' => 'Yoshi Griffith', 'slug' => 'yoshi-griffith']);
        $realBrand = DB::table('product_brands')->insertGetId(['name' => 'Caseificio Rossi', 'slug' => 'caseificio-rossi']);

        $fake = $this->company(110590, 'Cyphersol');
        $real = $this->company(109837, 'Gravity');

        $this->product($fake, 'Mag Child Prod', $fakeBrand);
        $testProduct = $this->product($real, 'Test');
        $realProduct = $this->product($real, 'Abbonamento mensile', $fakeBrand);

        $removed = (new LegacyTestData())->purge();

        $this->assertSame(['companies' => 1, 'users' => 1, 'products' => 2, 'brands' => 1], $removed);
        $this->assertDatabaseMissing('companies', ['id' => $fake->id]);
        $this->assertDatabaseMissing('users', ['id' => $fake->user_id]);
        $this->assertDatabaseMissing('products', ['id' => $testProduct]);
        $this->assertDatabaseHas('companies', ['id' => $real->id]);
        $this->assertDatabaseHas('products', ['id' => $realProduct, 'brand_id' => null]);
        $this->assertDatabaseHas('product_brands', ['id' => $realBrand]);
    }

    public function test_l_importazione_non_porta_la_marca_di_prova(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dump');

        file_put_contents($path, <<<'SQL'
        INSERT INTO `product_brands` (`id`, `name`, `slug`, `description`, `icon`, `created_at`, `updated_at`) VALUES
        (4, 'Caseificio Rossi', 'caseificio-rossi', NULL, NULL, '2025-12-01 10:00:00', '2025-12-01 10:00:00'),
        (5, 'Yoshi Griffith', 'yoshi-griffith', 'Voluptatem nulla of', 'uploads/brands/yoshi-griffith-1765175292.png', '2025-12-08 05:28:12', '2025-12-08 05:28:12');
        SQL);

        try {
            $this->artisan('legacy:import', ['file' => $path])
                ->expectsOutputToContain('Dati di prova tolti: aziende 0, utenti 0, prodotti 0, marche 1')
                ->assertSuccessful();
        } finally {
            @unlink($path);
        }

        $this->assertDatabaseHas('product_brands', ['id' => 4, 'slug' => 'caseificio-rossi']);
        $this->assertDatabaseMissing('product_brands', ['slug' => 'yoshi-griffith']);
    }
}
