<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Support\Images\ImageStore;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Le immagini caricate si salvano ridotte, in WebP, con la miniatura per gli elenchi.
 */
class ImageOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $plan = Plan::create([
            'name' => 'Ecommerce',
            'slug' => 'ecommerce',
            'price' => 349,
            'priority' => 40,
            'duration_days' => 365,
            'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOP],
        ]);

        $this->vendor = User::create([
            'name' => 'Venditore',
            'email' => 'venditore@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        $this->company = Company::create([
            'user_id' => $this->vendor->id,
            'plan_id' => $plan->id,
            'name' => 'Caseificio Rossi',
            'slug' => 'caseificio-rossi',
            'is_active' => true,
        ]);
    }

    private function product(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Mozzarella',
            'price' => 5,
            'stock' => 10,
            'product_type' => 'simple',
            'status' => 'active',
        ], $overrides);
    }

    public function test_la_foto_del_prodotto_diventa_un_webp_ridotto_con_miniatura(): void
    {
        $this->actingAs($this->vendor)
            ->post(route('vendor.products.store'), $this->product([
                'featured_image' => UploadedFile::fake()->image('foto.jpg', 4000, 3000),
            ]))
            ->assertSessionHasNoErrors();

        $path = Product::firstOrFail()->featured_image;
        $disk = Storage::disk('public');

        $this->assertStringStartsWith('products/', $path);
        $this->assertStringEndsWith('.webp', $path);
        $this->assertSame([1600, 1200], array_slice(getimagesize($disk->path($path)), 0, 2));

        $thumb = ImageStore::thumbPath($path);
        $disk->assertExists($thumb);
        $this->assertSame([600, 450], array_slice(getimagesize($disk->path($thumb)), 0, 2));
        $this->assertSame($thumb, ImageStore::thumb($path));
    }

    public function test_le_immagini_piccole_non_si_ingrandiscono(): void
    {
        $this->actingAs($this->vendor)
            ->post(route('vendor.products.store'), $this->product([
                'featured_image' => UploadedFile::fake()->image('piccola.png', 300, 200),
            ]))
            ->assertSessionHasNoErrors();

        $path = Product::firstOrFail()->featured_image;

        $this->assertSame([300, 200], array_slice(getimagesize(Storage::disk('public')->path($path)), 0, 2));
    }

    public function test_sostituire_la_foto_cancella_la_vecchia_e_la_sua_miniatura(): void
    {
        $this->actingAs($this->vendor)->post(route('vendor.products.store'), $this->product([
            'featured_image' => UploadedFile::fake()->image('prima.jpg', 1000, 1000),
        ]));
        $product = Product::firstOrFail();
        $old = $product->featured_image;

        $this->actingAs($this->vendor)
            ->put(route('vendor.products.update', $product), $this->product([
                'featured_image' => UploadedFile::fake()->image('dopo.jpg', 1000, 1000),
            ]))
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing([$old, ImageStore::thumbPath($old)]);
        Storage::disk('public')->assertExists($product->fresh()->featured_image);
    }

    public function test_un_file_che_non_e_un_immagine_viene_rifiutato(): void
    {
        $this->actingAs($this->vendor)
            ->post(route('vendor.products.store'), $this->product([
                'featured_image' => UploadedFile::fake()->create('listino.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('featured_image');

        $this->assertSame(0, Product::count());
    }

    public function test_logo_e_copertina_dal_profilo_azienda(): void
    {
        $this->actingAs($this->vendor)
            ->put(route('vendor.profile.update'), [
                'name' => 'Caseificio Rossi',
                'logo' => UploadedFile::fake()->image('logo.png', 2000, 2000),
                'banner' => UploadedFile::fake()->image('copertina.jpg', 3840, 2160),
            ])
            ->assertSessionHasNoErrors();

        $company = $this->company->fresh();
        $disk = Storage::disk('public');

        $this->assertSame([800, 800], array_slice(getimagesize($disk->path($company->logo)), 0, 2));
        $this->assertSame([2000, 1125], array_slice(getimagesize($disk->path($company->banner)), 0, 2));
        $disk->assertMissing(ImageStore::thumbPath($company->logo));
        $disk->assertExists(ImageStore::thumbPath($company->banner));
    }

    public function test_la_gif_animata_resta_intera(): void
    {
        // Due fotogrammi 1x1: basta a riconoscerla come animata.
        $gif = base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAIfkEAAAAAAAsAAAAAAEAAQAAAgJEAQA7');
        $file = UploadedFile::fake()->createWithContent('animata.gif', $gif);

        $path = app(ImageStore::class)->store($file, 'advertisements', 'advertisement');

        $this->assertStringEndsWith('.gif', $path);
        $this->assertSame($gif, Storage::disk('public')->get($path));
    }

    public function test_il_comando_converte_le_immagini_gia_caricate(): void
    {
        $disk = Storage::disk('public');
        $disk->put('products/vecchia.jpg', UploadedFile::fake()->image('vecchia.jpg', 3000, 3000)->getContent());
        $disk->put('companies/galleria/uno.jpg', UploadedFile::fake()->image('uno.jpg', 2400, 1800)->getContent());

        $product = Product::create($this->product(['company_id' => $this->company->id, 'slug' => 'mozzarella', 'featured_image' => 'products/vecchia.jpg']));
        $this->company->update(['offer_gallery' => ['companies/galleria/uno.jpg', 'companies/galleria/manca.jpg']]);

        $this->artisan('images:optimize', ['--dry-run' => true])
            ->expectsOutputToContain('Immagini da convertire: 2')
            ->assertSuccessful();
        $this->assertSame('products/vecchia.jpg', $product->fresh()->featured_image);

        $this->artisan('images:optimize', ['--delete-originals' => true])
            ->expectsOutputToContain('Immagini convertite: 2')
            ->assertSuccessful();

        $path = $product->fresh()->featured_image;
        $this->assertStringEndsWith('.webp', $path);
        $this->assertSame([1600, 1600], array_slice(getimagesize($disk->path($path)), 0, 2));
        $disk->assertMissing('products/vecchia.jpg');

        $gallery = $this->company->fresh()->offer_gallery;
        $this->assertStringEndsWith('.webp', $gallery[0]);
        $this->assertSame('companies/galleria/manca.jpg', $gallery[1]);

        $this->artisan('images:optimize')->expectsOutputToContain('Immagini convertite: 0')->assertSuccessful();
    }
}
