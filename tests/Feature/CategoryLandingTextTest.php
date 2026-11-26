<?php

namespace Tests\Feature;

use App\Models\CategoryLandingText;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Testi scritti in amministrazione per le pagine /categorie/...
 */
class CategoryLandingTextTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    private CompanyCategory $estetica;

    private CompanyCategory $auto;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->plan = Plan::create([
            'name' => 'Anagrafica', 'slug' => 'anagrafica', 'price' => 19, 'priority' => 10,
            'duration_days' => 365, 'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD],
        ]);

        $this->estetica = CompanyCategory::create(['name' => 'Centri Estetici', 'slug' => 'centri-estetici']);
        $this->auto = CompanyCategory::create(['name' => 'Autofficine', 'slug' => 'autofficine']);

        // Centri estetici: quattro a Roma. Autofficine: tre a Roma, ma meno ricche in tutto.
        foreach (['A', 'B', 'C', 'D'] as $letter) {
            $this->company("Estetica $letter", $this->estetica, 'Roma', 'Lazio');
        }

        foreach (['A', 'B', 'C'] as $letter) {
            $this->company("Officina $letter", $this->auto, 'Roma', 'Lazio');
        }
    }

    private function company(string $name, CompanyCategory $category, string $city, string $region): void
    {
        $user = User::create([
            'name' => $name, 'email' => Str::slug($name).'@example.test',
            'password' => 'password', 'user_type' => 'vendor',
        ]);

        Company::create([
            'user_id' => $user->id, 'plan_id' => $this->plan->id, 'category_id' => $category->id,
            'name' => $name, 'slug' => Str::slug($name), 'city' => $city, 'region' => $region, 'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super amministratore', 'is_system' => true]);

        return User::create([
            'name' => 'Amministratore', 'email' => 'admin'.uniqid().'@example.test', 'password' => 'password',
            'user_type' => 'admin', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function save(array $fields, ?string $place = 'roma', array $extra = [])
    {
        return $this->actingAs($this->admin())->put(
            route('admin.landing_texts.update'),
            ['categoria' => $this->estetica->id] + ($place !== null ? ['luogo' => $place] : []) + $fields + $extra
        );
    }

    public function test_chi_non_e_amministratore_non_entra(): void
    {
        $this->get(route('admin.landing_texts.index'))->assertRedirect();
    }

    public function test_l_elenco_mostra_le_pagine_piu_ricche_per_prime_con_lo_stato_del_testo(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.landing_texts.index'))
            ->assertOk()
            ->assertSeeInOrder(['Centri Estetici · tutta Italia', 'Autofficine · tutta Italia'])
            ->assertSee('Centri Estetici · Roma')
            ->assertSee('Automatico');

        $this->save(['intro' => 'Testo mio'])->assertRedirect();

        $this->actingAs($this->admin())
            ->get(route('admin.landing_texts.index', ['stato' => 'con']))
            ->assertOk()
            ->assertSee('Centri Estetici · Roma')
            ->assertSee('Scritto da te')
            ->assertDontSee('Autofficine · ');
    }

    public function test_il_modulo_mostra_il_testo_automatico_come_suggerimento(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.landing_texts.edit', ['categoria' => $this->estetica->id, 'luogo' => 'roma']))
            ->assertOk()
            ->assertSee('Centri Estetici a Roma')
            ->assertSee('Parti dal testo automatico');
    }

    public function test_il_testo_salvato_compare_sulla_pagina_pubblica_con_i_segnaposto(): void
    {
        $this->save([
            'heading' => 'I migliori {categoria} di {luogo}',
            'meta_title' => 'Centri estetici a {luogo}: {numero} indirizzi',
            'intro' => "Primo paragrafo su {luogo}.\n\nSecondo paragrafo.",
            'body' => 'Come scegliere un centro estetico a {luogo}.',
        ])->assertRedirect();

        $this->get(route('categories.show', ['category' => 'centri-estetici', 'place' => 'roma']))
            ->assertOk()
            ->assertSee('<h1 class="ksm-landing__title">I migliori Centri Estetici di Roma</h1>', false)
            ->assertSee('<title>Centri estetici a Roma: 4 indirizzi</title>', false)
            ->assertSee('Primo paragrafo su Roma.')
            ->assertSee('Secondo paragrafo.')
            ->assertSee('Come scegliere un centro estetico a Roma.');

        // Gli altri campi restano quelli automatici.
        $this->get(route('categories.show', ['category' => 'centri-estetici', 'place' => 'roma']))
            ->assertSee('"@type":"CollectionPage","name":"I migliori Centri Estetici di Roma"', false);
    }

    public function test_il_testo_e_solo_testo_senza_html(): void
    {
        $this->save(['intro' => '<script>alert(1)</script> ciao'])->assertRedirect();

        $this->get(route('categories.show', ['category' => 'centri-estetici', 'place' => 'roma']))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; ciao', false);
    }

    public function test_il_modello_vale_per_i_luoghi_ma_non_per_la_pagina_della_categoria_e_la_pagina_lo_scavalca(): void
    {
        $this->save(['intro' => 'Modello: {categoria} a {luogo}.'], CategoryLandingText::ALL_PLACES)->assertRedirect();

        $this->get(route('categories.show', ['category' => 'centri-estetici', 'place' => 'roma']))
            ->assertSee('Modello: Centri Estetici a Roma.');

        // La pagina della categoria non usa il modello dei luoghi.
        $this->get(route('categories.show', ['category' => 'centri-estetici']))
            ->assertDontSee('Modello:');

        // Un testo scritto per la pagina vince sul modello.
        $this->save(['intro' => 'Solo per Roma.'])->assertRedirect();

        $this->get(route('categories.show', ['category' => 'centri-estetici', 'place' => 'roma']))
            ->assertSee('Solo per Roma.')
            ->assertDontSee('Modello:');
    }

    public function test_salvare_tutto_vuoto_riporta_al_testo_automatico(): void
    {
        $this->save(['intro' => 'Testo mio'])->assertRedirect();
        $this->assertDatabaseCount('category_landing_texts', 1);

        $this->save(['intro' => ''])->assertRedirect();
        $this->assertDatabaseCount('category_landing_texts', 0);
    }

    public function test_torna_al_testo_automatico_toglie_il_testo(): void
    {
        $this->save(['intro' => 'Testo mio'])->assertRedirect();

        $this->actingAs($this->admin())
            ->delete(route('admin.landing_texts.destroy'), ['categoria' => $this->estetica->id, 'luogo' => 'roma'])
            ->assertRedirect();

        $this->assertDatabaseCount('category_landing_texts', 0);
    }

    public function test_salva_e_passa_alla_prossima_pagina_senza_testo(): void
    {
        // Ordine: Centri Estetici (tutta Italia, 4), Centri Estetici Roma e Lazio (4), poi le Autofficine (3).
        $response = $this->save(['intro' => 'Testo categoria'], null, ['vai_avanti' => 1]);

        $response->assertRedirect();
        $location = $response->headers->get('Location');

        // Dopo la categoria intera viene la sua pagina piu' ricca: va aperta una pagina con un luogo, non la stessa.
        $this->assertStringContainsString('categoria='.$this->estetica->id, $location);
        $this->assertStringContainsString('luogo=', $location);
    }

    public function test_un_luogo_senza_aziende_nella_categoria_da_404(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.landing_texts.edit', ['categoria' => $this->estetica->id, 'luogo' => 'napoli']))
            ->assertNotFound();
    }
}
