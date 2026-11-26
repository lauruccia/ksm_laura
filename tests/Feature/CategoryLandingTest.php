<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Plan;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pagine /categorie/{categoria} e /categorie/{categoria}/{citta-o-regione}.
 */
class CategoryLandingTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    private CompanyCategory $estetica;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->plan = Plan::create([
            'name' => 'Anagrafica',
            'slug' => 'anagrafica',
            'price' => 19,
            'priority' => 10,
            'duration_days' => 365,
            'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD],
        ]);

        $this->estetica = CompanyCategory::create(['name' => 'Centri Estetici', 'slug' => 'centri-estetici']);
    }

    private function company(string $name, CompanyCategory $category, ?string $city, ?string $region = null): Company
    {
        $user = User::create([
            'name' => $name,
            'email' => Str::slug($name).'@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        return Company::create([
            'user_id' => $user->id,
            'plan_id' => $this->plan->id,
            'category_id' => $category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'city' => $city,
            'region' => $region,
            'is_active' => true,
        ]);
    }

    /** Tre centri a Roma e uno a Milano: Roma ha abbastanza aziende per essere indicizzata. */
    private function seedEstetica(): void
    {
        foreach (['Bellezza Roma Uno', 'Bellezza Roma Due', 'Bellezza Roma Tre'] as $name) {
            $this->company($name, $this->estetica, 'Roma', 'Lazio');
        }

        $this->company('Bellezza Milano', $this->estetica, 'Milano', 'Lombardia');
    }

    public function test_la_pagina_categoria_elenca_le_aziende_con_titolo_e_h1(): void
    {
        $this->seedEstetica();

        $this->get(route('categories.show', ['category' => 'centri-estetici']))
            ->assertOk()
            ->assertSee('<h1 class="ksm-landing__title">Centri Estetici</h1>', false)
            ->assertSee('<title>Centri Estetici · elenco aziende | ', false)
            ->assertSee('Bellezza Roma Uno')
            ->assertSee('Bellezza Milano')
            // Altri luoghi: Roma ha tre aziende e merita il link; Milano ne ha una sola e no.
            ->assertSee(route('categories.show', ['category' => 'centri-estetici', 'place' => 'roma']), false)
            ->assertDontSee(route('categories.show', ['category' => 'centri-estetici', 'place' => 'milano']), false);
    }

    public function test_la_pagina_citta_mostra_solo_quella_citta(): void
    {
        $this->seedEstetica();

        $this->get(route('categories.show', ['category' => 'centri-estetici', 'place' => 'roma']))
            ->assertOk()
            ->assertSee('<h1 class="ksm-landing__title">Centri Estetici a Roma</h1>', false)
            ->assertSee('Bellezza Roma Uno')
            ->assertDontSee('Bellezza Milano')
            ->assertSee('<link rel="canonical" href="'.route('categories.show', ['category' => 'centri-estetici', 'place' => 'roma']).'">', false)
            ->assertDontSee('name="robots"', false);
    }

    public function test_la_pagina_regione_comprende_anche_chi_ha_la_regione_nella_colonna_della_citta(): void
    {
        $this->company('Estetica Lazio Uno', $this->estetica, 'Latina', 'Lazio');
        // Dato vecchio: la regione scritta al posto della citta'.
        $this->company('Estetica Lazio Due', $this->estetica, 'Lazio', null);
        $this->company('Estetica Lombarda', $this->estetica, 'Como', 'Lombardia');

        $this->get(route('categories.show', ['category' => 'centri-estetici', 'place' => 'lazio']))
            ->assertOk()
            ->assertSee('<h1 class="ksm-landing__title">Centri Estetici Lazio</h1>', false)
            ->assertSee('Estetica Lazio Uno')
            ->assertSee('Estetica Lazio Due')
            ->assertDontSee('Estetica Lombarda');

    }

    public function test_la_categoria_comprende_le_sottocategorie(): void
    {
        $viso = CompanyCategory::create(['name' => 'Trattamenti Viso', 'slug' => 'trattamenti-viso', 'parent_id' => $this->estetica->id]);
        $this->company('Viso Perfetto', $viso, 'Roma', 'Lazio');

        $this->get(route('categories.show', ['category' => 'centri-estetici']))
            ->assertOk()
            ->assertSee('Viso Perfetto')
            ->assertSee(route('categories.show', ['category' => 'trattamenti-viso']), false);

        // La sottocategoria non mostra le aziende della madre.
        $this->company('Solo Madre', $this->estetica, 'Roma', 'Lazio');
        $this->get(route('categories.show', ['category' => 'trattamenti-viso']))
            ->assertOk()
            ->assertSee('Viso Perfetto')
            ->assertDontSee('Solo Madre');
    }

    public function test_categoria_o_luogo_inesistenti_danno_404(): void
    {
        $this->seedEstetica();

        $this->get(route('categories.show', ['category' => 'non-esiste']))->assertNotFound();
        $this->get(route('categories.show', ['category' => 'centri-estetici', 'place' => 'napoli']))->assertNotFound();
        // Una regione senza aziende in quella categoria non e' una pagina.
        $this->get(route('categories.show', ['category' => 'centri-estetici', 'place' => 'sicilia']))->assertNotFound();
    }

    public function test_con_poche_aziende_la_pagina_non_si_indicizza(): void
    {
        $this->seedEstetica();

        $this->get(route('categories.show', ['category' => 'centri-estetici', 'place' => 'milano']))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_la_mappa_elenca_solo_le_pagine_con_abbastanza_aziende(): void
    {
        $this->seedEstetica();

        $index = $this->get('/sitemap.xml')->assertOk();
        $index->assertSee('sitemap-categorie-1.xml', false);

        $this->get('/sitemap-categorie-1.xml')
            ->assertOk()
            ->assertSee(route('categories.show', ['category' => 'centri-estetici']), false)
            ->assertSee(route('categories.show', ['category' => 'centri-estetici', 'place' => 'roma']), false)
            ->assertSee(route('categories.show', ['category' => 'centri-estetici', 'place' => 'lazio']), false)
            ->assertDontSee(route('categories.show', ['category' => 'centri-estetici', 'place' => 'milano']), false);
    }

    public function test_l_elenco_aziende_con_la_sola_categoria_rimanda_alla_pagina_pulita(): void
    {
        $this->seedEstetica();

        $landing = route('categories.show', ['category' => 'centri-estetici']);

        // Il vecchio indirizzo con l'id resta valido: rimanda in modo permanente a quello con il nome.
        $this->get(route('companies.index', ['categoria' => $this->estetica->id]))
            ->assertStatus(301)
            ->assertRedirect($landing);

        // Con un altro filtro resta l'elenco, senza rimando.
        $this->get(route('companies.index', ['categoria' => $this->estetica->id, 'citta' => 'Roma']))
            ->assertOk()
            ->assertDontSee('<link rel="canonical" href="'.$landing.'">', false);
    }
}
