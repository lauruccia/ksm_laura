<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $small = $this->plan('Base', 1, ['TELEFONO', 'E-Mail']);
        $big = $this->plan('Completo', 2, ['Telefono', 'E-mail', 'Negozio online']);

        $this->assertSame(
            ['Telefono', 'E-mail', 'Negozio online'],
            array_values(Plan::featureUnion([$small, $big]))
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
