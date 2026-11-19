<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La pagina dei piani mette a confronto le voci: ogni scheda elenca anche
 * quelle che il piano non comprende, segnate come mancanti.
 */
class PlanComparisonTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $name, int $priority, array $features): Plan
    {
        return Plan::create([
            'name' => $name,
            'slug' => strtolower($name),
            'price' => $priority * 100,
            'priority' => $priority,
            'is_active' => true,
            'features' => $features,
        ]);
    }

    public function test_l_elenco_parte_dal_piano_piu_ricco_e_non_ripete_le_voci(): void
    {
        $small = $this->plan('Base', 1, ['TELEFONO', 'Sito Web']);
        $big = $this->plan('Completo', 2, ['Telefono', 'Sito web', 'Negozio online']);

        $this->assertSame(
            ['Telefono', 'Sito web', 'Negozio online'],
            array_values(Plan::featureUnion([$small, $big]))
        );
    }

    public function test_le_voci_inglesi_del_vecchio_sito_si_leggono_in_italiano(): void
    {
        $legacy = $this->plan('Vetrina', 1, ['COMPANY NAME', 'PHONE', 'E-MAIL', 'LOGO', 'WORKING HOURS']);
        $italian = $this->plan('Ecommerce', 2, ['Ragione sociale', 'Telefono', 'Email', 'Logo', 'Orari di apertura', 'Ecommerce']);

        $this->assertSame(
            ['Ragione sociale', 'Telefono', 'Email', 'Logo', 'Orari di apertura'],
            $legacy->fresh()->features
        );
        $this->assertSame(
            ['Ragione sociale', 'Telefono', 'Email', 'Logo', 'Orari di apertura', 'Ecommerce'],
            array_values(Plan::featureUnion([$legacy, $italian]))
        );
    }

    public function test_un_piano_tradotto_a_mano_non_raddoppia_le_voci_degli_altri(): void
    {
        // Sul database le voci del vecchio sito restano in inglese finche' il piano non viene salvato.
        $this->plan('Vetrina', 1, []);
        DB::table('plans')->where('slug', 'vetrina')
            ->update(['features' => json_encode(['COMPANY NAME', 'PHONE', 'PERSONAL PAGE'])]);
        $this->plan('Ecommerce', 2, ['Ragione sociale', 'Telefono', 'Pagina Personale', 'Ecommerce']);

        $this->get(route('plans.index'))
            ->assertOk()
            ->assertSeeText('Non incluso: Ecommerce')
            ->assertDontSeeText('Non incluso: Ragione sociale')
            ->assertDontSeeText('Non incluso: Pagina Personale')
            ->assertDontSeeText('Company name')
            ->assertDontSeeText('Personal page');
    }

    public function test_salvando_un_piano_le_voci_inglesi_e_i_doppioni_spariscono(): void
    {
        $plan = $this->plan('Biglietto', 1, ['Address', 'Indirizzo', 'e-commerce', ' ', 'Sito web']);

        $this->assertSame(
            ['Indirizzo', 'Ecommerce', 'Sito web'],
            json_decode(DB::table('plans')->where('id', $plan->id)->value('features'), true)
        );
    }

    public function test_il_piano_piccolo_mostra_le_voci_che_non_ha(): void
    {
        $this->plan('Base', 1, ['Telefono']);
        $this->plan('Completo', 2, ['Telefono', 'Negozio online']);

        $this->get(route('plans.index'))
            ->assertOk()
            ->assertSeeText('Non incluso: Negozio online')
            ->assertDontSeeText('Non incluso: Telefono');
    }
}
