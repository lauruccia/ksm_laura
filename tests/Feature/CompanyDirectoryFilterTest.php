<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Plan;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Il filtro per categoria dell'elenco aziende comprende le sottocategorie.
 */
class CompanyDirectoryFilterTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::create([
            'name' => 'Anagrafica',
            'slug' => 'anagrafica',
            'price' => 19,
            'priority' => 10,
            'duration_days' => 365,
            'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD],
        ]);
    }

    private function company(string $name, CompanyCategory $category): void
    {
        $user = User::create([
            'name' => $name,
            'email' => Str::slug($name).'@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        Company::create([
            'user_id' => $user->id,
            'plan_id' => $this->plan->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ]);
    }

    public function test_una_categoria_comprende_le_sue_sottocategorie_a_ogni_livello(): void
    {
        $root = CompanyCategory::create(['name' => 'Costruire e Abitare', 'slug' => 'costruire-e-abitare']);
        $middle = CompanyCategory::create(['name' => 'Imprese Edili', 'slug' => 'imprese-edili', 'parent_id' => $root->id]);
        $leaf = CompanyCategory::create(['name' => 'Amianto', 'slug' => 'amianto', 'parent_id' => $middle->id]);
        $other = CompanyCategory::create(['name' => 'Ristoranti', 'slug' => 'ristoranti']);

        $this->company('Edilnord Costruzioni', $middle);
        $this->company('Bonifiche Sicure', $leaf);
        $this->company('Trattoria da Mario', $other);

        $this->get(route('companies.index', ['categoria' => $root->id]))
            ->assertOk()
            ->assertSee('Edilnord Costruzioni')
            ->assertSee('Bonifiche Sicure')
            ->assertDontSee('Trattoria da Mario')
            ->assertSee('Costruire e Abitare › Imprese Edili › Amianto');

        $this->get(route('companies.index', ['categoria' => $leaf->id]))
            ->assertOk()
            ->assertSee('Bonifiche Sicure')
            ->assertDontSee('Edilnord Costruzioni');
    }

    public function test_il_menu_laterale_apre_il_ramo_scelto_e_conserva_la_ricerca(): void
    {
        $root = CompanyCategory::create(['name' => 'Costruire e Abitare', 'slug' => 'costruire-e-abitare']);
        $leaf = CompanyCategory::create(['name' => 'Imprese Edili', 'slug' => 'imprese-edili', 'parent_id' => $root->id]);
        $other = CompanyCategory::create(['name' => 'Ristoranti', 'slug' => 'ristoranti']);

        $this->company('Edilnord Costruzioni', $leaf);

        $html = $this->get(route('companies.index', ['categoria' => $leaf->id, 'cerca' => 'edil']))
            ->assertOk()
            ->assertSeeInOrder(['Costruire e Abitare', 'Tutto in Costruire e Abitare', 'Imprese Edili', 'Ristoranti'])
            ->getContent();

        // Il ramo della categoria scelta e' aperto, gli altri no.
        $this->assertMatchesRegularExpression('/<details class="ksm-dirnav__group"\s+open\s*>\s*<summary[^>]*>\s*<span>Costruire e Abitare/', $html);

        // La voce attiva e' segnata e i link cambiano solo la categoria.
        $this->assertMatchesRegularExpression('/is-current[^>]*href="[^"]*categoria='.$leaf->id.'[^"]*"\s+aria-current="page"/', $html);
        $this->assertStringContainsString(e(route('companies.index', ['cerca' => 'edil', 'categoria' => $other->id])), $html);
    }
}
