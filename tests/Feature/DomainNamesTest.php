<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainName;
use App\Models\Role;
use App\Models\User;
use App\Support\DomainNameList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pagina pubblica dei nomi a dominio e loro gestione in amministrazione. */
class DomainNamesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => Role::SUPER_ADMIN],
            ['name' => 'Super amministratore', 'is_system' => true]
        );

        return User::create([
            'name' => 'Amministratore',
            'email' => 'admin'.uniqid().'@example.test',
            'password' => 'password',
            'user_type' => 'admin',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function seedNames(): void
    {
        foreach (['pub.it', 'pizzerie.it', 'ristorantikosher.com', 'sexyshopfirenze.it', '7vie.it'] as $host) {
            DomainName::create(['name' => $host, 'category' => DomainNameList::theme($host)]);
        }
    }

    public function test_il_nome_si_ripulisce_e_l_estensione_si_ricava(): void
    {
        $this->assertSame('pub.it', DomainNameList::normalize(' WWW.Pub.IT '));
        $this->assertSame('gelateriacarnevali.it', DomainNameList::normalize('www.gelateriacarnevali.it(SentireGov)'));
        $this->assertSame('pub.it', DomainNameList::normalize('https://www.pub.it/pagina?x=1'));
        $this->assertNull(DomainNameList::normalize('non un dominio'));

        $domain = DomainName::create(['name' => 'WWW.Pub.it']);

        $this->assertSame('pub.it', $domain->name);
        $this->assertSame('it', $domain->extension);
        $this->assertSame('www.pub.it', $domain->display);
    }

    public function test_la_lettura_dell_elenco_ignora_il_testo_e_segnala_gli_scarti(): void
    {
        $parsed = DomainNameList::parse("Ad esempio chi digita www.pub.it raggiunge Ksm.\nwww.pizzerie.it, www.pub.it\nwww.-sbagliato-.it");

        $this->assertSame(['pub.it', 'pizzerie.it'], array_values($parsed['valid']));
        $this->assertSame(['www.-sbagliato-.it'], $parsed['invalid']);
    }

    public function test_la_pagina_pubblica_elenca_i_nomi(): void
    {
        $this->seedNames();
        DomainName::create(['name' => 'nascosto.it', 'is_active' => false]);

        $this->get(route('domain_names.index'))
            ->assertOk()
            ->assertSee('http://www.pub.it', false)
            ->assertSee('www.7vie.it')
            ->assertDontSee('www.nascosto.it');
    }

    public function test_ricerca_filtri_e_ordinamento(): void
    {
        $this->seedNames();

        $this->get(route('domain_names.index', ['cerca' => 'pizz']))
            ->assertSee('www.pizzerie.it')->assertDontSee('http://www.pub.it', false);

        $this->get(route('domain_names.index', ['estensione' => 'com']))
            ->assertSee('www.ristorantikosher.com')->assertDontSee('http://www.pub.it', false);

        $this->get(route('domain_names.index', ['tema' => 'Sexy shop']))
            ->assertSee('www.sexyshopfirenze.it')->assertDontSee('www.pizzerie.it');

        $this->get(route('domain_names.index', ['iniziale' => 'p']))
            ->assertSee('http://www.pub.it', false)->assertSee('www.pizzerie.it')->assertDontSee('www.7vie.it');

        $this->get(route('domain_names.index', ['iniziale' => '0']))
            ->assertSee('www.7vie.it')->assertDontSee('http://www.pub.it', false);

        $this->get(route('domain_names.index', ['ordina' => 'corti']))
            ->assertSeeInOrder(['http://www.pub.it', 'http://www.7vie.it'], false)
            ->assertSeeInOrder(['http://www.pub.it', 'http://www.ristorantikosher.com'], false);

        // Una ricerca con % non fa da jolly.
        $this->get(route('domain_names.index', ['cerca' => '%']))->assertDontSee('http://www.pub.it', false);
    }

    public function test_l_admin_aggiunge_un_nome_e_non_i_doppioni(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.domain_names.store'), ['name' => 'WWW.Birrifici.it', 'is_active' => '1'])
            ->assertRedirect(route('admin.domain_names.index'));

        $domain = DomainName::where('name', 'birrifici.it')->firstOrFail();
        $this->assertSame('Ristoranti e cibo', $domain->category);

        $this->actingAs($admin)->post(route('admin.domain_names.store'), ['name' => 'birrifici.it'])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)->put(route('admin.domain_names.update', $domain), ['name' => 'birrifici.it', 'category' => 'Altro', 'is_active' => '0'])
            ->assertRedirect();
        $this->assertFalse($domain->refresh()->is_active);
        $this->assertSame('Altro', $domain->category);
    }

    public function test_l_admin_incolla_un_elenco(): void
    {
        $this->seedNames();

        $this->actingAs($this->admin())
            ->get(route('admin.domain_names.import'))->assertOk();

        $this->actingAs($this->admin())->post(route('admin.domain_names.import.store'), [
            'elenco' => "www.pub.it\nwww.nuovo.it\nwww.nuovo.it\nwww.hotelroma.com",
        ])->assertRedirect(route('admin.domain_names.index'))->assertSessionHas('success');

        $this->assertSame(7, DomainName::count());
        $this->assertSame('Alberghi e hotel', DomainName::where('name', 'hotelroma.com')->value('category'));
    }

    public function test_l_elenco_admin_si_apre_e_si_cerca(): void
    {
        $this->seedNames();

        $this->actingAs($this->admin())
            ->get(route('admin.domain_names.index', ['cerca' => 'pub']))
            ->assertOk()->assertSee('pub.it')->assertDontSee('pizzerie.it');
    }

    public function test_un_visitatore_non_entra_nell_admin(): void
    {
        $this->get(route('admin.domain_names.index'))->assertRedirect();
    }

    public function test_punta_a_ksm_crea_i_domini_non_attivi_senza_doppioni(): void
    {
        $this->seedNames();
        $pub = DomainName::where('name', 'pub.it')->firstOrFail();
        $pizzerie = DomainName::where('name', 'pizzerie.it')->firstOrFail();
        Domain::create(['name' => 'Pizzerie', 'domain' => 'pizzerie.it']);

        $this->actingAs($this->admin())->patch(route('admin.domain_names.bulk'), [
            'action' => 'point',
            'scope' => 'selected',
            'ids' => [$pub->id, $pizzerie->id],
        ])->assertRedirect()->assertSessionHas('success');

        $domain = Domain::where('domain', 'pub.it')->firstOrFail();
        $this->assertFalse($domain->is_active);
        $this->assertSame('home', $domain->type);
        $this->assertSame(1, Domain::where('domain', 'pizzerie.it')->count());
        $this->assertSame(2, Domain::count());
    }

    public function test_punta_a_ksm_vale_anche_su_tutti_i_risultati(): void
    {
        $this->seedNames();

        $this->actingAs($this->admin())->patch(route('admin.domain_names.bulk'), [
            'action' => 'point',
            'scope' => 'all',
            'cerca' => 'kosher',
        ])->assertRedirect();

        $this->assertSame(['ristorantikosher.com'], Domain::pluck('domain')->all());
    }

    public function test_l_elenco_admin_mostra_lo_stato_del_puntamento(): void
    {
        $this->seedNames();
        Domain::create(['name' => 'Pub', 'domain' => 'pub.it', 'is_active' => false]);

        $this->actingAs($this->admin())
            ->get(route('admin.domain_names.index'))
            ->assertOk()
            ->assertSee('Da verificare (non attivo)')
            ->assertSee('Non puntato');
    }

    public function test_eliminare_in_blocco_funziona_ancora(): void
    {
        $this->seedNames();
        $ids = DomainName::whereIn('name', ['pub.it', '7vie.it'])->pluck('id')->all();

        $this->actingAs($this->admin())->patch(route('admin.domain_names.bulk'), [
            'action' => 'delete', 'scope' => 'selected', 'ids' => $ids,
        ])->assertRedirect();

        $this->assertSame(3, DomainName::count());
    }
}
