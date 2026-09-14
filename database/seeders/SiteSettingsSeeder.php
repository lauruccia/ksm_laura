<?php

namespace Database\Seeders;

use App\Models\AdminSetting;
use Illuminate\Database\Seeder;

/**
 * Dati del gestore del sito: Gruppo Kosmos.
 *
 * Separati dai dati di prova perche' vanno ripristinati anche dopo
 * `legacy:import`, che riparte da tabelle vuote e lascia fuori le
 * impostazioni:
 *
 *     php artisan db:seed --class=SiteSettingsSeeder
 *
 * Compila solo i campi vuoti: non sovrascrive quanto gia' modificato
 * dall'amministrazione.
 */
class SiteSettingsSeeder extends Seeder
{
    private const COMPANY = [
        'website_name' => 'KSM',
        'company_name' => 'Gruppo Kosmos',
        'vat_number' => '18138671005',
        'website_url' => 'https://www.ksm.it',
        'website_email' => 'info@ksm.it',
        'contact_number' => '+39067821653',
        'address' => 'Via Eurialo, 56, 00181 Roma RM',
        'about' => 'Marketplace e directory per aziende e prodotti.',
    ];

    public function run(): void
    {
        $settings = AdminSetting::current();

        foreach (self::COMPANY as $field => $value) {
            if (blank($settings->$field)) {
                $settings->$field = $value;
            }
        }

        $settings->save();
    }
}
