<?php

namespace Database\Seeders;

use App\Models\AdminPaymentSetting;
use App\Models\AdminSetting;
use App\Models\CmsPage;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyPaymentSetting;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Dati minimi per far girare il sito in locale.
 *
 * I dati del gestore del sito sono quelli reali di Gruppo Kosmos.
 * Utenti, aziende e prodotti invece sono di prova: vanno sostituiti
 * prima della messa online.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Dati veri del gestore, in un seeder a parte da rilanciare dopo legacy:import.
        $this->call(SiteSettingsSeeder::class);

        // Spedizione di prova, solo se l'amministrazione non ne ha impostata una.
        $settings = AdminSetting::current();
        if ($settings->base_shipping_rate === null) {
            $settings->update(['base_shipping_rate' => 5.90]);
        }

        AdminPaymentSetting::firstOrCreate([], ['mode' => 'test']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.test'],
            ['name' => 'Amministratore', 'password' => 'password', 'user_type' => 'admin']
        );

        // I piani non li crea il seeder: li inserisce l'amministratore
        // da Piani, con le quote vere. Finche' non ce n'e' uno, le
        // aziende di prova restano spente e fuori dalla directory.

        $categories = collect(['Alimentari', 'Servizi', 'Artigianato', 'Tecnologia'])
            ->map(fn ($name) => CompanyCategory::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]));

        $productCategories = collect(['Casa', 'Cibo e bevande', 'Elettronica', 'Energia'])
            ->map(fn ($name) => ProductCategory::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]));

        $brands = collect(['Marca A', 'Marca B'])
            ->map(fn ($name) => ProductBrand::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]));

        $places = [
            ['city' => 'Roma', 'region' => 'Lazio'],
            ['city' => 'Milano', 'region' => 'Lombardia'],
            ['city' => 'Torino', 'region' => 'Piemonte'],
            ['city' => 'Napoli', 'region' => 'Campania'],
        ];

        foreach (range(1, 6) as $i) {
            $vendor = User::firstOrCreate(
                ['email' => "azienda$i@example.test"],
                ['name' => "Titolare $i", 'password' => 'password', 'user_type' => 'vendor']
            );

            $place = $places[($i - 1) % count($places)];

            $company = Company::firstOrCreate(
                ['slug' => "azienda-dimostrativa-$i"],
                [
                    'user_id' => $vendor->id,
                    'category_id' => $categories->random()->id,
                    'name' => "Azienda dimostrativa $i",
                    'city' => $place['city'],
                    'region' => $place['region'],
                    'address' => "Via Esempio $i",
                    'email' => "azienda$i@example.test",
                    'phone' => '000 0000000',
                    'company_description' => 'Scheda di prova creata dal seeder.',
                    'is_active' => false,
                    'base_shipping_rate' => 4.90,
                ]
            );

            CompanyPaymentSetting::firstOrCreate(
                ['company_id' => $company->id],
                ['mode' => 'test', 'enable_kmoney' => true, 'kmoney_test_account' => 'DEMO-'.$i]
            );

            foreach (range(1, 4) as $j) {
                Product::firstOrCreate(
                    ['slug' => "prodotto-$i-$j"],
                    [
                        'company_id' => $company->id,
                        'category_id' => $productCategories->random()->id,
                        'brand_id' => $brands->random()->id,
                        'name' => "Prodotto di prova $i.$j",
                        'short_description' => 'Descrizione breve di prova.',
                        'price' => 19.90 * $j,
                        'stock' => 10,
                        'status' => 'active',
                    ]
                );
            }
        }

        CmsPage::firstOrCreate(
            ['slug' => 'chi-siamo'],
            [
                'title' => 'Chi siamo',
                'content' => '<p>Pagina di prova.</p>',
                'status' => 'published',
                'visibility' => 'visible',
                'locations' => ['footer'],
                'published_at' => now(),
            ]
        );

        $this->command?->info("Amministratore: {$admin->email} / password");
    }
}
