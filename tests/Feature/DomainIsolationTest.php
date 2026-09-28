<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\CmsPage;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Domain;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ogni dominio e' un sito a se': da un sito non si vede, non si apre e non
 * si scrive nulla che appartenga a un altro. Un test per ogni via da cui
 * un dato potrebbe passare da un sito all'altro.
 */
class DomainIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const MAIN = 'http://localhost';

    private const NETWORK = 'http://cibocalabrese.test';

    private const OTHER_NETWORK = 'http://scarpe.test';

    private const COMPANY_SITE = 'http://trattoriamario.test';

    private Company $trattoria;

    private Company $calzature;

    private Domain $network;

    private Domain $otherNetwork;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::create([
            'name' => 'Completo', 'slug' => 'completo', 'price' => 99, 'priority' => 20, 'duration_days' => 365, 'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOWCASE, PlanCapabilities::SHOP],
        ]);

        $food = ProductCategory::create(['name' => 'Cibo', 'slug' => 'cibo']);
        $shoes = ProductCategory::create(['name' => 'Scarpe', 'slug' => 'scarpe']);
        $restaurants = CompanyCategory::create(['name' => 'Ristoranti', 'slug' => 'ristoranti']);
        $shoemakers = CompanyCategory::create(['name' => 'Calzaturifici', 'slug' => 'calzaturifici']);

        $this->trattoria = $this->company('Trattoria Mario', $plan, $restaurants, $food, [
            'custom_domain' => 'trattoriamario.test', 'email' => 'mario@trattoria.test', 'phone' => '0965 111111',
        ]);
        $this->calzature = $this->company('Calzature Rossi', $plan, $shoemakers, $shoes);

        $this->network = Domain::create([
            'name' => 'Cibo calabrese', 'domain' => 'cibocalabrese.test', 'type' => 'category',
            'product_category_id' => $food->id, 'company_category_id' => $restaurants->id,
            'company_scope' => 'category', 'entry_page' => 'home', 'is_active' => true, 'email' => 'info@cibocalabrese.test',
        ]);
        $this->otherNetwork = Domain::create([
            'name' => 'Scarpe', 'domain' => 'scarpe.test', 'type' => 'category',
            'product_category_id' => $shoes->id, 'company_category_id' => $shoemakers->id,
            'company_scope' => 'category', 'entry_page' => 'home', 'is_active' => true,
        ]);

        AdminSetting::current()->update(['company_name' => 'Gruppo Kosmos', 'website_email' => 'info@ksm.test', 'vat_number' => '01234567890']);
    }

    private function company(string $name, Plan $plan, CompanyCategory $category, ProductCategory $productCategory, array $extra = []): Company
    {
        $owner = User::create(['name' => $name, 'email' => Str::slug($name).'@example.test', 'password' => 'password', 'user_type' => 'vendor']);

        $company = Company::create([
            'user_id' => $owner->id, 'plan_id' => $plan->id, 'category_id' => $category->id,
            'name' => $name, 'slug' => Str::slug($name), 'is_active' => true,
        ] + $extra);

        Product::create([
            'company_id' => $company->id, 'category_id' => $productCategory->id, 'name' => "Prodotto $name",
            'slug' => Str::slug("prodotto $name"), 'price' => 10, 'stock' => 5, 'status' => 'active',
        ]);

        return $company;
    }

    private function buyer(): User
    {
        return User::create(['name' => 'Cliente', 'email' => 'cliente@example.test', 'password' => 'password', 'user_type' => 'buyer', 'is_active' => true]);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super amministratore', 'is_system' => true]);

        return User::create([
            'name' => 'Amministratore', 'email' => 'admin@example.test', 'password' => 'password',
            'user_type' => 'admin', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function order(User $user, Company $company, array $site): Order
    {
        return Order::create([
            'user_id' => $user->id, 'company_id' => $company->id, 'subtotal' => 10, 'total' => 10,
            'currency' => 'EUR', 'status' => 'paid', 'billing_email' => $user->email,
        ] + $site);
    }

    // Host sconosciuti

    public function test_un_dominio_non_registrato_risponde_404_senza_marchio(): void
    {
        // Anche indirizzi che non esistono, con parametri o riservati a chi ha fatto accesso.
        foreach (['/', '/prodotti', '/aziende', '/accedi', '/prodotti/inesistente', '/aziende/calzature-rossi', '/account/ordini/1', '/area-azienda', '/non/esiste'] as $path) {
            $this->get('http://qualsiasi-altro.test'.$path)
                ->assertNotFound()
                ->assertSee('Sito non trovato.')
                ->assertDontSee('KSM')
                ->assertDontSee('Prodotto Calzature Rossi');
        }
    }

    public function test_un_dominio_spento_smette_di_rispondere_e_uno_nuovo_risponde_subito(): void
    {
        $this->get(self::NETWORK.'/')->assertOk();

        $this->network->update(['is_active' => false]);
        $this->get(self::NETWORK.'/')->assertNotFound();

        Domain::create(['name' => 'Nuovo', 'domain' => 'nuovo.test', 'type' => 'home', 'company_scope' => 'category', 'entry_page' => 'home', 'is_active' => true]);
        $this->get('http://nuovo.test/')->assertOk();
    }

    // Dominio proprio di un'azienda

    public function test_sul_dominio_dell_azienda_ci_sono_solo_i_suoi_prodotti(): void
    {
        $this->get(self::COMPANY_SITE.'/prodotti')
            ->assertOk()
            ->assertSee('Prodotto Trattoria Mario')
            ->assertDontSee('Prodotto Calzature Rossi');

        $this->get(self::COMPANY_SITE.'/prodotti/prodotto-calzature-rossi')->assertNotFound();
        $this->get(self::COMPANY_SITE.'/aziende/calzature-rossi')->assertNotFound();
        $this->get(self::COMPANY_SITE.'/aziende')->assertRedirect(self::COMPANY_SITE);
    }

    public function test_il_dominio_dell_azienda_non_mostra_nulla_di_ksm(): void
    {
        $page = $this->get(self::COMPANY_SITE.'/prodotti')->assertOk();

        $page->assertDontSee('Gruppo Kosmos')
            ->assertDontSee('01234567890')
            ->assertDontSee('info@ksm.test')
            ->assertDontSee(route('register.vendor', [], false), false)
            ->assertDontSee(route('plans.index', [], false).'"', false)
            ->assertSee('Trattoria Mario');

        $this->get(self::COMPANY_SITE.'/contatti')
            ->assertOk()
            ->assertSee('mario@trattoria.test')
            ->assertDontSee('info@ksm.test');
    }

    public function test_la_mappa_del_dominio_dell_azienda_ha_solo_le_sue_pagine(): void
    {
        $index = $this->get(self::COMPANY_SITE.'/sitemap-prodotti-1.xml')->assertOk()->getContent();
        $this->assertStringContainsString('prodotto-trattoria-mario', $index);
        $this->assertStringNotContainsString('prodotto-calzature-rossi', $index);

        $this->get(self::COMPANY_SITE.'/sitemap-aziende-1.xml')->assertNotFound();

        $pages = $this->get(self::COMPANY_SITE.'/sitemap-pagine-1.xml')->assertOk()->getContent();
        $this->assertStringNotContainsString('/piani', $pages);
        $this->assertStringNotContainsString('/aziende', $pages);
    }

    // Pagine CMS

    public function test_le_pagine_cms_restano_sul_loro_sito(): void
    {
        CmsPage::create(['title' => 'Chi siamo KSM', 'slug' => 'chi-siamo', 'content' => 'Siamo Gruppo Kosmos', 'status' => 'published', 'visibility' => 'visible']);
        CmsPage::create(['sites' => [$this->network->id], 'title' => 'Chi siamo', 'slug' => 'chi-siamo', 'content' => 'Cucina calabrese', 'status' => 'published', 'visibility' => 'visible']);
        CmsPage::create(['sites' => [$this->network->id], 'title' => 'Ricette', 'slug' => 'ricette', 'content' => 'Le ricette', 'status' => 'published', 'visibility' => 'visible']);

        // Stesso slug, un contenuto per sito.
        $this->get(self::MAIN.'/chi-siamo')->assertOk()->assertSee('Siamo Gruppo Kosmos')->assertDontSee('Cucina calabrese');
        $this->get(self::NETWORK.'/chi-siamo')->assertOk()->assertSee('Cucina calabrese')->assertDontSee('Gruppo Kosmos');

        // Le pagine di un dominio non si aprono altrove.
        $this->get(self::MAIN.'/ricette')->assertNotFound();
        $this->get(self::OTHER_NETWORK.'/ricette')->assertNotFound();
        $this->get(self::OTHER_NETWORK.'/chi-siamo')->assertNotFound();
        $this->get(self::COMPANY_SITE.'/chi-siamo')->assertNotFound();

        $this->assertStringContainsString('/ricette', $this->get(self::NETWORK.'/sitemap-pagine-1.xml')->getContent());
        $this->assertStringNotContainsString('/ricette', $this->get(self::MAIN.'/sitemap-pagine-1.xml')->getContent());
        $this->assertStringNotContainsString('/ricette', $this->get(self::OTHER_NETWORK.'/sitemap-pagine-1.xml')->getContent());
    }

    public function test_la_stessa_pagina_si_vede_su_tutti_i_siti_scelti(): void
    {
        $page = CmsPage::create([
            'sites' => ['platform', $this->network->id], 'title' => 'Spedizioni', 'slug' => 'spedizioni',
            'content' => 'Spediamo in 48 ore', 'status' => 'published', 'visibility' => 'visible',
        ]);

        $this->get(self::MAIN.'/spedizioni')->assertOk()->assertSee('Spediamo in 48 ore');
        $this->get(self::NETWORK.'/spedizioni')->assertOk()->assertSee('Spediamo in 48 ore');
        $this->get(self::OTHER_NETWORK.'/spedizioni')->assertNotFound();

        // In amministrazione l'elenco dice dove sta, il modulo ha le caselle spuntate.
        $admin = $this->admin();
        $this->actingAs($admin)->get(self::MAIN.route('admin.cms.index', [], false))->assertOk()->assertSee('Sito principale, cibocalabrese.test');
        $this->actingAs($admin)->get(self::MAIN.route('admin.cms.edit', $page, false))->assertOk()
            ->assertSee('value="platform"', false)
            ->assertSee('name="sites[]" value="'.$this->network->id.'"', false);
        $this->actingAs($admin)->get(self::MAIN.route('admin.cms.create', [], false))->assertOk();

        // Tolto il sito principale dal modulo, resta solo sul dominio.
        $this->actingAs($admin)->put(self::MAIN.route('admin.cms.update', $page, false), [
            'sites' => [(string) $this->network->id, (string) $this->otherNetwork->id], 'title' => 'Spedizioni', 'slug' => 'spedizioni',
            'status' => 'published', 'visibility' => 'visible', 'sort_order' => 0,
        ])->assertSessionHasNoErrors();

        $this->get(self::MAIN.'/spedizioni')->assertNotFound();
        $this->get(self::OTHER_NETWORK.'/spedizioni')->assertOk();
        $this->assertEqualsCanonicalizing([(string) $this->network->id, (string) $this->otherNetwork->id], $page->fresh()->sites);
    }

    public function test_lo_slug_e_unico_dentro_il_sito_non_fra_i_siti(): void
    {
        $admin = $this->admin();
        CmsPage::create(['title' => 'Chi siamo KSM', 'slug' => 'chi-siamo', 'status' => 'published', 'visibility' => 'visible']);

        $page = ['title' => 'Chi siamo', 'slug' => 'chi-siamo', 'status' => 'published', 'visibility' => 'visible', 'sort_order' => 0];

        $this->actingAs($admin)->post(self::MAIN.route('admin.cms.store', [], false), $page + ['sites' => [(string) $this->network->id]])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(self::MAIN.route('admin.cms.store', [], false), $page + ['sites' => ['platform']])
            ->assertSessionHasErrors('slug');
        // Basta un sito in comune per lo scontro, anche insieme ad altri liberi.
        $this->actingAs($admin)->post(self::MAIN.route('admin.cms.store', [], false), $page + ['sites' => [(string) $this->otherNetwork->id, (string) $this->network->id]])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, CmsPage::whereHas('domains', fn ($q) => $q->whereKey($this->network->id))->where('slug', 'chi-siamo')->count());
    }

    // Aziende esclusive di un dominio

    public function test_un_azienda_esclusiva_si_vede_solo_sul_suo_dominio(): void
    {
        $this->trattoria->exclusiveDomains()->sync([$this->network->id]);
        // Anche fuori dal filtro del dominio: i calzaturifici non sono ristoranti.
        $this->calzature->exclusiveDomains()->sync([$this->network->id]);

        // Su KSM non c'e', nemmeno scrivendo l'indirizzo.
        $this->get(self::MAIN.'/aziende')->assertOk()->assertDontSee('Trattoria Mario')->assertDontSee('Calzature Rossi');
        $this->get(self::MAIN.'/prodotti')->assertOk()->assertDontSee('Prodotto Trattoria Mario');
        $this->get(self::MAIN.'/aziende/trattoria-mario')->assertNotFound();
        $this->get(self::MAIN.'/prodotti/prodotto-trattoria-mario')->assertNotFound();
        $this->assertStringNotContainsString('trattoria-mario', $this->get(self::MAIN.'/sitemap-aziende-1.xml')->getContent());

        // Sul suo dominio si', anche l'azienda fuori filtro; i prodotti seguono la categoria del dominio.
        $this->get(self::NETWORK.'/aziende')->assertOk()->assertSee('Trattoria Mario')->assertSee('Calzature Rossi');
        $this->get(self::NETWORK.'/prodotti/prodotto-trattoria-mario')->assertOk();
        $this->get(self::NETWORK.'/prodotti/prodotto-calzature-rossi')->assertNotFound();

        // Sull'altro dominio, che per filtro la comprenderebbe, no.
        $this->get(self::OTHER_NETWORK.'/aziende')->assertOk()->assertDontSee('Calzature Rossi');
        $this->get(self::OTHER_NETWORK.'/prodotti/prodotto-calzature-rossi')->assertNotFound();

        // Il suo dominio proprio resta suo.
        $this->get(self::COMPANY_SITE.'/prodotti')->assertOk()->assertSee('Prodotto Trattoria Mario');
    }

    public function test_un_azienda_puo_essere_esclusiva_di_piu_domini(): void
    {
        $this->calzature->exclusiveDomains()->sync([$this->network->id, $this->otherNetwork->id]);

        // Su tutti e due i suoi domini si', anche fuori filtro; su KSM no.
        $this->get(self::NETWORK.'/aziende')->assertOk()->assertSee('Calzature Rossi');
        $this->get(self::OTHER_NETWORK.'/aziende')->assertOk()->assertSee('Calzature Rossi');
        $this->get(self::MAIN.'/aziende')->assertOk()->assertDontSee('Calzature Rossi');
        $this->get(self::MAIN.'/aziende/calzature-rossi')->assertNotFound();
    }

    public function test_eliminare_il_dominio_spegne_le_sue_aziende_esclusive(): void
    {
        $this->calzature->exclusiveDomains()->sync([$this->network->id]);
        // Esclusiva anche di un altro dominio: resta accesa e resta li'.
        $this->trattoria->exclusiveDomains()->sync([$this->network->id, $this->otherNetwork->id]);

        $this->network->delete();

        $this->assertFalse($this->calzature->fresh()->is_active);
        $this->assertSame([], $this->calzature->fresh()->exclusiveDomains->modelKeys());
        $this->get(self::MAIN.'/aziende/calzature-rossi')->assertNotFound();

        $this->assertTrue($this->trattoria->fresh()->is_active);
        $this->assertSame([$this->otherNetwork->id], $this->trattoria->fresh()->exclusiveDomains->modelKeys());
        $this->get(self::MAIN.'/aziende/trattoria-mario')->assertNotFound();
    }

    // Sezioni della piattaforma

    public function test_le_sezioni_di_ksm_non_esistono_sugli_altri_siti(): void
    {
        $admin = $this->admin();
        $vendor = $this->calzature->user;

        foreach ([self::NETWORK, self::COMPANY_SITE] as $site) {
            // Anche da ospite: 404, non un invito ad accedere.
            foreach (['/area-azienda', '/abbonamento', '/attivazione', '/area-inserzionista', '/amministrazione'] as $path) {
                $this->get($site.$path)->assertNotFound();
            }

            $this->get($site.'/piani')->assertNotFound();
            $this->get($site.'/registrati/azienda')->assertNotFound();
            $this->get($site.'/inserzionisti/registrati')->assertNotFound();
            $this->get($site.'/registrati')->assertRedirect($site.'/registrati/privato');
        }

        foreach ([self::NETWORK, self::COMPANY_SITE] as $site) {
            $this->actingAs($admin)->get($site.'/amministrazione')->assertNotFound();
            $this->actingAs($vendor)->get($site.'/area-azienda')->assertNotFound();
            $this->actingAs($vendor)->get($site.'/attivazione')->assertNotFound();
            $this->actingAs($vendor)->get($site.'/abbonamento')->assertNotFound();
            $this->actingAs($vendor)->get($site.'/area-inserzionista')->assertNotFound();
        }

        // Sul sito principale ci sono.
        $this->get(self::MAIN.'/piani')->assertOk();
        $this->actingAs($admin)->get(self::MAIN.'/amministrazione')->assertOk();
    }

    public function test_le_stesse_credenziali_valgono_su_ogni_sito(): void
    {
        $this->buyer();

        foreach ([self::NETWORK, self::COMPANY_SITE, self::MAIN] as $site) {
            $this->post($site.'/accedi', ['email' => 'cliente@example.test', 'password' => 'password'])
                ->assertRedirect();
            $this->assertAuthenticated();
            auth()->logout();
        }
    }

    // Ordini

    public function test_ordini_e_traccia_ordine_restano_sul_sito_dove_sono_nati(): void
    {
        $buyer = $this->buyer();

        $onNetwork = $this->order($buyer, $this->trattoria, ['site' => 'domain', 'domain_id' => $this->network->id]);
        $onCompany = $this->order($buyer, $this->trattoria, ['site' => 'company']);
        $onMain = $this->order($buyer, $this->calzature, ['site' => 'platform']);

        $sites = [
            self::NETWORK => $onNetwork,
            self::COMPANY_SITE => $onCompany,
            self::MAIN => $onMain,
        ];

        foreach ($sites as $site => $own) {
            $this->actingAs($buyer)->get($site.'/account/ordini/'.$own->id)->assertOk();

            foreach ($sites as $other) {
                if ($other->isNot($own)) {
                    $this->actingAs($buyer)->get($site.'/account/ordini/'.$other->id)->assertNotFound();

                    $this->post($site.'/traccia-ordine', ['reference' => (string) $other->id, 'email' => $buyer->email])
                        ->assertOk()->assertViewHas('notFound', true);
                }
            }

            $this->post($site.'/traccia-ordine', ['reference' => (string) $own->id, 'email' => $buyer->email])
                ->assertOk()->assertViewHas('notFound', false);
        }

        // L'altro dominio della rete non vede nessuno dei tre.
        $this->actingAs($buyer)->get(self::OTHER_NETWORK.'/account')->assertOk()->assertViewHas('orderCount', 0);
        $this->actingAs($buyer)->get(self::NETWORK.'/account')->assertOk()->assertViewHas('orderCount', 1);
    }

    public function test_gli_elenchi_ordini_dicono_da_quale_sito_arrivano(): void
    {
        $buyer = $this->buyer();
        $this->order($buyer, $this->trattoria, ['site' => 'domain', 'domain_id' => $this->network->id]);
        $this->order($buyer, $this->trattoria, ['site' => 'company']);
        $this->order($buyer, $this->calzature, ['site' => 'platform']);

        $this->actingAs($this->admin())->get(self::MAIN.route('admin.orders.index', [], false))
            ->assertOk()
            ->assertSee('<th>Sito</th>', false)
            ->assertSee('cibocalabrese.test')
            ->assertSee('trattoriamario.test');

        $this->actingAs($this->trattoria->user)->get(self::MAIN.route('vendor.orders.index', [], false))
            ->assertOk()
            ->assertSee('<th>Sito</th>', false)
            ->assertSee('cibocalabrese.test')
            ->assertSee('trattoriamario.test');
    }

    public function test_un_ordine_nuovo_prende_il_sito_da_cui_si_compra(): void
    {
        $this->get(self::NETWORK.'/');
        $this->assertSame(['site' => 'domain', 'domain_id' => $this->network->id], Order::siteFields());

        $this->get(self::COMPANY_SITE.'/');
        $this->assertSame(['site' => 'company', 'domain_id' => null], Order::siteFields());

        $this->get(self::MAIN.'/');
        $this->assertSame(['site' => 'platform', 'domain_id' => null], Order::siteFields());
    }

    // Moduli

    public function test_contatti_e_recensioni_non_arrivano_ad_aziende_di_altri_siti(): void
    {
        $message = ['name' => 'Anna', 'email' => 'anna@example.test', 'message' => 'Buongiorno'];
        $review = ['name' => 'Anna', 'rating' => 5, 'comment' => 'Ottimo'];

        $this->post(self::NETWORK.'/aziende/calzature-rossi/contatto', $message)->assertNotFound();
        $this->post(self::NETWORK.'/aziende/calzature-rossi/recensioni', $review)->assertNotFound();
        $this->post(self::COMPANY_SITE.'/aziende/calzature-rossi/contatto', $message)->assertNotFound();

        $this->actingAs($this->buyer())
            ->post(self::NETWORK.'/prodotti/prodotto-calzature-rossi/recensioni', ['rating' => 5, 'comment' => 'Ottimo'])
            ->assertNotFound();

        // Sul sito giusto il modulo funziona.
        $this->post(self::NETWORK.'/aziende/trattoria-mario/recensioni', $review)->assertRedirect();
    }

    // Lingue

    public function test_il_catalogo_si_apre_anche_in_inglese(): void
    {
        $this->withCookie('locale', 'en')->get(self::MAIN.'/lingua/en');
        $this->get(self::MAIN.'/prodotti')->assertOk();
        $this->assertSame("Our customers' favourites", trans('storefront.featured_subtitle', [], 'en'));
    }
}
