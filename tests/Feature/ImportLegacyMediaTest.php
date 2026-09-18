<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Il dump porta i percorsi delle immagini, non i file: questo comando
 * li chiede al vecchio sito e li salva dove il sito nuovo li cerca.
 */
class ImportLegacyMediaTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        $plan = Plan::create([
            'name' => 'Ecommerce', 'slug' => 'ecommerce', 'price' => 2400, 'priority' => 40,
            'duration_days' => 365, 'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOWCASE, PlanCapabilities::SHOP],
        ]);

        $user = User::create([
            'name' => 'Titolare', 'email' => 'titolare@example.test',
            'password' => 'password', 'user_type' => 'vendor',
        ]);

        return Company::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'name' => 'Gelateria Bianchi',
            'slug' => 'gelateria-bianchi',
            'banner' => 'uploads/companies/banner/banner.jpg',
            'logo' => 'uploads/companies/logo/logo.png',
            'offer_gallery' => ['uploads/companies/gallery/uno.jpg', 'uploads/companies/gallery/due.jpg'],
            'is_active' => true,
        ]);
    }

    public function test_scarica_le_immagini_nominate_dal_database(): void
    {
        Storage::fake('public');
        Http::fake(['ksm.test/*' => Http::response('immagine', 200, ['Content-Type' => 'image/jpeg'])]);

        $company = $this->company();

        Product::create([
            'company_id' => $company->id, 'name' => 'Coppa', 'slug' => 'coppa',
            'price' => 3, 'stock' => 5, 'status' => 'active',
            'featured_image' => 'uploads/products/featured/coppa.png',
            'gallery_images' => ['uploads/products/gallery/coppa-2.png'],
        ]);

        $this->artisan('legacy:import-media', ['--from' => 'https://ksm.test'])
            ->expectsOutputToContain('File nominati dal database: 6')
            ->assertSuccessful();

        Storage::disk('public')->assertExists('uploads/companies/banner/banner.jpg');
        Storage::disk('public')->assertExists('uploads/companies/gallery/due.jpg');
        Storage::disk('public')->assertExists('uploads/products/gallery/coppa-2.png');

        Http::assertSent(fn ($request) => $request->url() === 'https://ksm.test/uploads/companies/logo/logo.png');
    }

    public function test_i_file_gia_presenti_si_saltano(): void
    {
        Storage::fake('public');
        Http::fake(['ksm.test/*' => Http::response('immagine', 200, ['Content-Type' => 'image/jpeg'])]);

        $this->company();
        Storage::disk('public')->put('uploads/companies/banner/banner.jpg', 'gia-qui');

        $this->artisan('legacy:import-media', ['--from' => 'https://ksm.test'])
            ->expectsOutputToContain('Da scaricare: 3')
            ->assertSuccessful();

        $this->assertSame('gia-qui', Storage::disk('public')->get('uploads/companies/banner/banner.jpg'));
    }

    public function test_una_pagina_di_errore_non_diventa_un_immagine(): void
    {
        Storage::fake('public');
        Http::fake(['ksm.test/*' => Http::response('<html>non trovato</html>', 404, ['Content-Type' => 'text/html'])]);

        $this->company();

        $this->artisan('legacy:import-media', ['--from' => 'https://ksm.test', '--only' => 'aziende'])
            ->expectsOutputToContain('Assenti sul vecchio sito: 4')
            ->assertSuccessful();

        Storage::disk('public')->assertMissing('uploads/companies/banner/banner.jpg');
    }

    public function test_senza_indirizzo_il_comando_spiega_cosa_serve(): void
    {
        $this->artisan('legacy:import-media')
            ->expectsOutputToContain('Manca l\'indirizzo del sito originale.')
            ->assertFailed();
    }

    public function test_la_prova_a_vuoto_non_scarica_niente(): void
    {
        Storage::fake('public');
        Http::fake();

        $this->company();

        $this->artisan('legacy:import-media', ['--from' => 'https://ksm.test', '--dry-run' => true])
            ->assertSuccessful();

        Http::assertNothingSent();
        Storage::disk('public')->assertMissing('uploads/companies/banner/banner.jpg');
    }
}
