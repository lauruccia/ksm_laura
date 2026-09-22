<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Un dominio della rete come sito a se': filtro, pagina iniziale, contenuti e piede.
 */
class DomainSiteTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://mozzarelledibufala.test';

    private Plan $plan;

    private ProductCategory $dairy;

    private ProductCategory $buffalo;

    private ProductCategory $shoes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::create([
            'name' => 'Piano completo', 'slug' => 'completo', 'price' => 99, 'priority' => 20,
            'duration_days' => 365, 'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOWCASE, PlanCapabilities::SHOP],
        ]);

        $this->dairy = ProductCategory::create(['name' => 'Latticini', 'slug' => 'latticini']);
        $this->buffalo = ProductCategory::create(['name' => 'Mozzarelle di bufala', 'slug' => 'mozzarelle', 'parent_id' => $this->dairy->id]);
        $this->shoes = ProductCategory::create(['name' => 'Scarpe', 'slug' => 'scarpe']);
    }

    private function company(string $name): Company
    {
        $user = User::create(['name' => $name, 'email' => uniqid().'@example.test', 'password' => 'password', 'user_type' => 'vendor']);

        return Company::create(['user_id' => $user->id, 'plan_id' => $this->plan->id, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'is_active' => true]);
    }

    private function product(Company $company, string $name, ProductCategory $category): Product
    {
        return Product::create([
            'company_id' => $company->id, 'category_id' => $category->id, 'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name), 'price' => 14.9, 'stock' => 5, 'status' => 'active',
        ]);
    }

    private function domain(array $attributes = []): Domain
    {
        return Domain::create(array_merge([
            'name' => 'Mozzarelle di bufala', 'domain' => 'mozzarelledibufala.test', 'type' => 'category',
            'product_category_id' => $this->dairy->id, 'company_scope' => 'products', 'entry_page' => 'shop', 'is_active' => true,
        ], $attributes));
    }

    public function test_il_dominio_apre_lo_shop_filtrato_sulla_sua_categoria(): void
    {
        $caseificio = $this->company('Caseificio Aurora');
        $calzature = $this->company('Calzature Rossi');
        $this->product($caseificio, 'Mozzarella di Bufala DOP', $this->buffalo);
        $boot = $this->product($calzature, 'Stivale in pelle', $this->shoes);
        $this->domain();

        $this->get(self::HOST.'/')
            ->assertOk()
            ->assertSee('Mozzarella di Bufala DOP')
            ->assertDontSee('Stivale in pelle')
            // I riquadri sono le sottocategorie della categoria del dominio.
            ->assertSee('Mozzarelle di bufala')
            ->assertDontSee('Scarpe');

        // Fuori dal filtro non si vede e non si compra, nemmeno con l'indirizzo diretto.
        $this->get(self::HOST.'/prodotti/'.$boot->slug)->assertNotFound();
        $this->post(self::HOST.'/carrello/aggiungi/'.$boot->slug)->assertNotFound();
        $this->get(self::HOST.'/prodotti?categoria='.$this->shoes->id)->assertOk()->assertDontSee('Stivale in pelle');

        // Sul sito principale resta tutto.
        $this->get('http://localhost/prodotti')->assertOk()->assertSee('Stivale in pelle')->assertSee('Mozzarella di Bufala DOP');
        $this->get('http://localhost/prodotti/'.$boot->slug)->assertOk();
    }

    public function test_le_aziende_sono_solo_quelle_con_prodotti_nella_categoria(): void
    {
        $caseificio = $this->company('Caseificio Aurora');
        $calzature = $this->company('Calzature Rossi');
        $this->company('Azienda senza prodotti');
        $this->product($caseificio, 'Burrata', $this->buffalo);
        $this->product($calzature, 'Sandalo', $this->shoes);
        $this->domain();

        $this->get(self::HOST.'/aziende')
            ->assertOk()
            ->assertSee('Caseificio Aurora')
            ->assertDontSee('Calzature Rossi')
            ->assertDontSee('Azienda senza prodotti');

        $this->get(self::HOST.'/aziende/'.$calzature->slug)->assertNotFound();
        $this->get(self::HOST.'/aziende/'.$caseificio->slug)->assertOk();
        $this->get('http://localhost/aziende/'.$calzature->slug)->assertOk();
    }

    public function test_contenuti_menu_e_piede_sono_quelli_del_dominio(): void
    {
        $this->product($this->company('Caseificio Aurora'), 'Ricotta di bufala', $this->buffalo);
        $this->domain([
            'phone' => '081 555 1234',
            'header_accent' => '#2f9e44',
            'site' => [
                'hero' => ['title' => 'La vera Mozzarella di Bufala,', 'highlight' => 'fresca a casa tua.', 'script' => 'Il gusto della tradizione', 'badge_title' => '100%', 'badge_text' => 'Latte di bufala italiano', 'badge_flag' => true],
                'benefits' => ['items' => [['icon' => 'truck', 'title' => 'Spedizione refrigerata', 'text' => 'Freschezza garantita']]],
                'menu' => ['left' => [['label' => 'Offerte', 'url' => '/prodotti?offerta=1']]],
                'footer' => ['about' => 'Specialità artigianali dalla Campania.', 'legal' => 'Caseificio Aurora srl · P. IVA 01234567890'],
                'seo' => ['title' => 'Mozzarella di bufala fresca online'],
            ],
        ]);

        $this->get(self::HOST.'/')
            ->assertOk()
            ->assertSee('<title>Mozzarella di bufala fresca online</title>', false)
            ->assertSee('fresca a casa tua.')
            ->assertSee('Il gusto della tradizione')
            ->assertSee('Latte di bufala italiano')
            ->assertSee('Spedizione refrigerata')
            ->assertSee('Offerte')
            ->assertSee('--ksm-accent:#2f9e44', false)
            ->assertSee('Specialità artigianali dalla Campania.')
            ->assertSee('Caseificio Aurora srl')
            ->assertSee('081 555 1234')
            ->assertSee('ksm-footer--site', false)
            // Niente di KSM: piani e registrazione aziende restano sul sito principale.
            ->assertDontSee('/piani"', false)
            ->assertDontSee('/registrati/azienda"', false);
    }

    public function test_senza_contenuti_il_sito_principale_non_cambia(): void
    {
        // I blocchi della vetrina sono dei domini: il sito principale apre il
        // catalogo con l'impostazione della directory delle aziende.
        $this->get('http://localhost/prodotti')
            ->assertOk()
            ->assertSee('ksm-directory__search', false)
            ->assertDontSee(__('storefront.hero_title'))
            ->assertDontSee('ksm-store-hero', false)
            ->assertDontSee('ksm-footer--site', false)
            ->assertDontSee('ksm-store-featured', false);
    }

    public function test_sottotitolo_e_motto_del_sito_principale_vengono_dalle_impostazioni(): void
    {
        \App\Models\AdminSetting::current()->update(['header_tagline' => 'Il Portale dei Portali', 'header_subline' => 'Uno · Due']);

        $this->get('http://localhost/')
            ->assertOk()
            ->assertSee('Il Portale dei Portali')
            ->assertSee('Uno')
            ->assertDontSee(trim(__('site.claim_line1').' '.__('site.claim_line2')));

        // Un dominio della rete tiene i suoi testi.
        $this->domain();
        $this->get(self::HOST.'/prodotti')->assertOk()->assertDontSee('Il Portale dei Portali');
    }

    public function test_la_foto_di_apertura_del_sito_principale_si_carica_dalle_impostazioni(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()->assertSee('name="hero_image"', false);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'website_name' => 'KSM',
            'hero_image' => UploadedFile::fake()->image('hero.jpg', 1600, 800),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $image = \App\Models\AdminSetting::current()->hero_image;
        Storage::disk('public')->assertExists($image);
        $this->get('http://localhost/')->assertOk()->assertSee('storage/'.$image, false);

        // Un dominio della rete senza foto sua non prende quella del sito principale.
        $this->domain(['entry_page' => 'home']);
        $this->get(self::HOST.'/')->assertOk()->assertDontSee('storage/'.$image, false);

        // Togliendola si cancella anche il file.
        $this->actingAs($admin)->put(route('admin.settings.update'), ['website_name' => 'KSM', 'remove_hero_image' => '1'])
            ->assertRedirect();
        $this->assertNull(\App\Models\AdminSetting::current()->hero_image);
        Storage::disk('public')->assertMissing($image);
    }

    public function test_il_segno_piu_venduto_e_la_fila_in_evidenza(): void
    {
        $caseificio = $this->company('Caseificio Aurora');
        $best = $this->product($caseificio, 'Treccia di bufala', $this->buffalo);
        $best->update(['weight_kg' => 0.5]);
        $this->product($caseificio, 'Bocconcini', $this->buffalo);

        $buyer = User::create(['name' => 'Cliente', 'email' => 'cliente@example.test', 'password' => 'password', 'user_type' => 'buyer']);
        $order = \App\Models\Order::create(['user_id' => $buyer->id, 'company_id' => $caseificio->id, 'subtotal' => 30, 'total' => 30, 'status' => 'paid']);
        $order->items()->create(['product_id' => $best->id, 'product_name' => $best->name, 'product_price' => 15, 'quantity' => 2, 'subtotal' => 30]);

        $this->domain(['site' => ['featured' => ['title' => 'I più venduti', 'sort' => 'bestsellers']]]);

        $this->get(self::HOST.'/')
            ->assertOk()
            ->assertSee('ksm-store-featured', false)
            ->assertSee('I più venduti')
            ->assertSee(__('storefront.bestseller'))
            ->assertSee('(500 g)');
    }

    public function test_il_modulo_del_dominio_si_apre_e_salva_i_contenuti(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.domains.create'))->assertOk()->assertSee("Mostra l'apertura nello shop", false);

        $this->actingAs($admin)->post(route('admin.domains.store'), [
            'name' => 'Mozzarelle di bufala',
            'domain' => 'https://www.MozzarelleDiBufala.it/',
            'is_active' => '1',
            'type' => 'category',
            'product_category_id' => $this->dairy->id,
            'company_scope' => 'products',
            'entry_page' => 'shop',
            'header_accent' => '#2f9e44',
            'logo' => UploadedFile::fake()->image('logo.png', 400, 200),
            'hero_image' => UploadedFile::fake()->image('hero.jpg', 1600, 800),
            'site' => [
                'hero' => ['enabled' => '1', 'title' => 'La vera Mozzarella', 'badge_flag' => '1'],
                'benefits' => ['enabled' => '1', 'items' => [
                    ['icon' => 'truck', 'title' => 'Spedizione refrigerata', 'text' => 'In tutta Italia'],
                    ['icon' => 'leaf', 'title' => '', 'text' => ''],
                ]],
                'categories' => ['enabled' => '1', 'ids' => [$this->buffalo->id], 'offers' => '0'],
                'featured' => ['enabled' => '1', 'sort' => 'newest', 'count' => '6', 'search' => '1'],
                'catalog' => ['enabled' => '0'],
                'menu' => ['left' => [['label' => 'Shop', 'url' => '/prodotti'], ['label' => '', 'url' => '']]],
                'footer' => ['links' => [['label' => 'Privacy', 'url' => 'javascript:alert(1)']]],
            ],
        ])->assertSessionHasErrors('site.footer.links.0.url');

        $this->actingAs($admin)->post(route('admin.domains.store'), [
            'name' => 'Mozzarelle di bufala',
            'domain' => 'https://www.MozzarelleDiBufala.it/',
            'is_active' => '1',
            'type' => 'category',
            'product_category_id' => $this->dairy->id,
            'company_scope' => 'products',
            'entry_page' => 'shop',
            'logo' => UploadedFile::fake()->image('logo.png', 400, 200),
            'hero_image' => UploadedFile::fake()->image('hero.jpg', 1600, 800),
            'site' => [
                'hero' => ['enabled' => '1', 'title' => 'La vera Mozzarella', 'badge_flag' => '1'],
                'benefits' => ['enabled' => '1', 'items' => [
                    ['icon' => 'truck', 'title' => 'Spedizione refrigerata', 'text' => 'In tutta Italia'],
                    ['icon' => 'leaf', 'title' => '', 'text' => ''],
                ]],
                'categories' => ['enabled' => '1', 'ids' => [$this->buffalo->id], 'offers' => '0'],
                'menu' => ['left' => [['label' => 'Shop', 'url' => '/prodotti'], ['label' => '', 'url' => '']]],
                'footer' => ['links' => [['label' => 'Privacy', 'url' => '/privacy']]],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.domains.index'));

        $domain = Domain::firstOrFail();
        $this->assertSame('mozzarelledibufala.it', $domain->domain);
        $this->assertSame('shop', $domain->entry_page);
        $this->assertNotNull($domain->logo);
        Storage::disk('public')->assertExists($domain->logo);
        Storage::disk('public')->assertExists($domain->site['hero']['image']);
        $this->assertCount(1, $domain->site['benefits']['items']);
        $this->assertSame([['label' => 'Shop', 'url' => '/prodotti']], $domain->site['menu']['left']);
        $this->assertSame([$this->buffalo->id], $domain->site['categories']['ids']);

        // Un secondo salvataggio senza file tiene l'immagine gia' caricata.
        $image = $domain->site['hero']['image'];
        $this->actingAs($admin)->put(route('admin.domains.update', $domain), [
            'name' => 'Mozzarelle di bufala', 'domain' => 'mozzarelledibufala.it', 'is_active' => '1',
            'type' => 'category', 'company_scope' => 'products', 'entry_page' => 'shop',
            'site' => ['hero' => ['title' => 'Nuovo titolo']],
        ])->assertSessionHasNoErrors();
        $this->assertSame($image, $domain->fresh()->site['hero']['image']);

        $this->actingAs($admin)->get(route('admin.domains.edit', $domain))->assertOk()->assertSee('Nuovo titolo');
    }

    public function test_ristoranti_di_una_regione_e_solo_i_loro_voucher(): void
    {
        $restaurants = \App\Models\CompanyCategory::create(['name' => 'Ristoranti', 'slug' => 'ristoranti']);
        $pizzerie = \App\Models\CompanyCategory::create(['name' => 'Pizzerie', 'slug' => 'pizzerie', 'parent_id' => $restaurants->id]);
        $shops = \App\Models\CompanyCategory::create(['name' => 'Negozi', 'slug' => 'negozi']);
        $voucher = ProductCategory::create(['name' => 'Voucher', 'slug' => 'voucher']);

        $place = fn (string $name, $category, string $city, string $region) => tap($this->company($name))
            ->update(['category_id' => $category->id, 'city' => $city, 'region' => $region]);

        $tropea = $place('Trattoria Tropea', $restaurants, 'Tropea', 'Calabria');
        $cosenza = $place('Pizzeria Cosenza', $pizzerie, 'Cosenza', 'Calabria');
        $roma = $place('Osteria Roma', $restaurants, 'Roma', 'Lazio');
        $negozio = $place('Souvenir Reggio', $shops, 'Reggio Calabria', 'Calabria');

        $this->product($tropea, 'Voucher cena per due', $voucher);
        $this->product($tropea, 'Nduja piccante', $this->buffalo);
        $this->product($roma, 'Voucher pranzo romano', $voucher);
        $this->product($negozio, 'Voucher souvenir', $voucher);

        $this->domain([
            'name' => 'Ristoranti Calabria', 'domain' => 'ristoranticalabria.test', 'type' => 'category+city',
            'city' => 'calabria', 'company_category_id' => $restaurants->id, 'product_category_id' => $voucher->id,
            'company_scope' => 'category', 'entry_page' => 'companies',
        ]);

        // Aziende: i ristoranti calabresi, sottocategorie comprese, anche senza voucher.
        $this->get('http://ristoranticalabria.test/')
            ->assertOk()
            ->assertSee('Trattoria Tropea')
            ->assertSee('Pizzeria Cosenza')
            ->assertDontSee('Osteria Roma')
            ->assertDontSee('Souvenir Reggio');

        // Prodotti: solo i voucher dei ristoranti calabresi.
        $this->get('http://ristoranticalabria.test/prodotti')
            ->assertOk()
            ->assertSee('Voucher cena per due')
            ->assertDontSee('Nduja piccante')
            ->assertDontSee('Voucher pranzo romano')
            ->assertDontSee('Voucher souvenir');

        $this->assertNotNull($cosenza);
    }

    public function test_www_rimanda_all_indirizzo_senza_www(): void
    {
        $this->domain();

        $this->get('http://www.mozzarelledibufala.test/prodotti?offerta=1')
            ->assertStatus(301)
            ->assertRedirect('http://mozzarelledibufala.test/prodotti?offerta=1');

        // Un modulo inviato a www non si perde in un redirect.
        $this->assertNotSame(301, $this->post('http://www.mozzarelledibufala.test/contatti')->getStatusCode());
        $this->get('http://mozzarelledibufala.test/prodotti')->assertOk()
            ->assertSee('<link rel="canonical" href="http://mozzarelledibufala.test/prodotti">', false);
    }

    public function test_gli_header_del_proxy_valgono_solo_dal_proxy_fidato(): void
    {
        $this->domain();

        // Caddy sulla stessa macchina: https e dominio veri negli X-Forwarded-*.
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'mozzarelledibufala.test', 'X-Forwarded-Port' => '443'])
            ->get('http://127.0.0.1/prodotti')
            ->assertOk()
            ->assertSee('ksm-footer--site', false)
            ->assertSee('action="https://mozzarelledibufala.test/prodotti', false);

        // Da fuori gli stessi header non contano: resta il sito principale, in http.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'mozzarelledibufala.test'])
            ->get('http://localhost/prodotti')
            ->assertOk()
            ->assertDontSee('ksm-footer--site', false)
            ->assertDontSee('https://mozzarelledibufala.test', false);
    }

    public function test_la_home_usa_i_vantaggi_del_dominio_e_senza_luogo_tiene_il_filtro_regioni(): void
    {
        $domain = $this->domain([
            'type' => 'city', 'city' => 'Ostia', 'product_category_id' => null, 'entry_page' => 'home',
            'site' => ['benefits' => ['items' => [['icon' => 'pin', 'title' => 'Aziende del quartiere', 'text' => 'A due passi da casa'], ['icon' => 'star', 'title' => '']]]],
        ]);

        $this->get(self::HOST.'/')
            ->assertOk()
            ->assertSee('Aziende del quartiere')
            ->assertSee('A due passi da casa')
            ->assertSee('--usp-count: 1', false)
            ->assertDontSee(__('site.usp_marketplace'))
            // Il dominio mostra gia' solo Ostia: niente menu delle regioni.
            ->assertDontSee('name="regione"', false);

        $domain->update(['site' => ['benefits' => ['enabled' => false]]]);
        $this->get(self::HOST.'/')->assertOk()->assertDontSee('ksm-usp__item', false)->assertSee('ksm-usp-wrap--flat', false);

        // Il sito principale tiene i suoi riquadri e il filtro per regione.
        $this->get('http://localhost/')->assertOk()->assertSee(__('site.usp_marketplace'))->assertSee('name="regione"', false);

        $this->actingAs($this->admin())->get(route('admin.domains.edit', $domain))->assertOk()->assertSee('Home e shop.');
    }

    public function test_la_citta_del_dominio_vale_come_parola_intera(): void
    {
        foreach (['Ostia', 'Lido di Ostia', 'Ostia Antica', 'Ostiano', 'Ostia-Lido'] as $city) {
            $this->company('Azienda '.$city)->update(['city' => $city]);
        }
        $domain = $this->domain(['type' => 'city', 'city' => 'Ostia', 'product_category_id' => null, 'company_scope' => 'category']);

        $cities = (new \App\Support\Sites\SiteScope($domain))->companies(Company::query())->pluck('city')->sort()->values()->all();

        $this->assertSame(['Lido di Ostia', 'Ostia', 'Ostia Antica', 'Ostia-Lido'], $cities);
    }

    public function test_banner_scelti_dal_dominio_e_citta_della_regione(): void
    {
        $campaign = fn (array $attributes) => \App\Models\Advertisement::create($attributes + [
            'name' => 'Campagna', 'link' => 'https://example.test', 'img' => 'advertisements/x.png',
            'locations' => [\App\Support\Ads\Placements::HOME_BELOW_HEADER], 'billing' => 'period', 'status' => 1,
        ]);
        $cosenza = $this->company('Trattoria Cosenza');
        $cosenza->update(['city' => 'Cosenza', 'region' => 'Calabria']);

        $domain = $this->domain(['type' => 'city', 'city' => 'Calabria', 'product_category_id' => null]);
        $ovunque = $campaign([]);
        $mirata = $campaign(['target_domains' => [$domain->id]]);
        $aCosenza = $campaign(['target_cities' => ['Cosenza']]);
        $aRoma = $campaign(['target_cities' => ['Roma']]);

        app(\App\Support\TenantContext::class)->useDomain($domain);
        $context = \App\Support\Ads\AdContext::current();

        // Un dominio di regione vale come le sue citta'.
        $this->assertTrue($aCosenza->matches($context));
        $this->assertFalse($aRoma->matches($context));
        $this->assertTrue($ovunque->matches($context));

        $domain->update(['site' => ['ads' => ['mode' => 'targeted']]]);
        app(\App\Support\TenantContext::class)->useDomain($domain->fresh());
        $context = \App\Support\Ads\AdContext::current();
        $this->assertFalse($ovunque->matches($context));
        $this->assertTrue($mirata->matches($context));

        $domain->update(['site' => ['ads' => ['mode' => 'none']]]);
        app(\App\Support\TenantContext::class)->useDomain($domain->fresh());
        $this->assertFalse($mirata->matches(\App\Support\Ads\AdContext::current()));
    }

    public function test_contatti_ed_email_portano_il_nome_e_l_indirizzo_del_dominio(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        \App\Models\AdminSetting::current()->update(['website_email' => 'info@ksm.test', 'address' => 'Sede KSM']);
        $this->domain(['email' => 'ordini@mozzarelledibufala.test', 'address' => 'Via dei Caseifici 12']);

        $this->get(self::HOST.'/contatti')
            ->assertOk()
            ->assertSee('Via dei Caseifici 12')
            ->assertSee('ordini@mozzarelledibufala.test')
            ->assertDontSee('Sede KSM')
            ->assertDontSee('info@ksm.test');

        $this->post(self::HOST.'/contatti', ['name' => 'Anna', 'email' => 'anna@example.test', 'message' => 'Spedite a Milano?'])
            ->assertSessionHasNoErrors();

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\ContactMessage::class, function ($mail) {
            return $mail->hasTo('ordini@mozzarelledibufala.test')
                && $mail->siteName === 'Mozzarelle di bufala'
                && $mail->envelope()->subject === 'Nuovo messaggio da Mozzarelle di bufala';
        });

        // La richiesta dopo, sul sito principale, non eredita il nome del dominio.
        $this->get('http://localhost/contatti')->assertOk()->assertSee('info@ksm.test');
        $this->assertNotSame('Mozzarelle di bufala', config('mail.from.name'));
        $this->assertNotSame('Mozzarelle di bufala', config('app.name'));
    }

    public function test_il_dominio_proprio_di_un_azienda_apre_la_sua_pagina(): void
    {
        $company = $this->company('Caseificio Aurora');
        $company->update(['custom_domain' => 'caseificioaurora.test', 'company_description' => '<p>Dal 1952 a Battipaglia.</p>']);
        $this->company('Calzature Rossi');

        $this->get('http://caseificioaurora.test/')
            ->assertOk()
            ->assertSee('Dal 1952 a Battipaglia.')
            ->assertDontSee('Calzature Rossi');

        // La stessa pagina al suo indirizzo lungo rimanda all'indirizzo principale.
        $this->get('http://caseificioaurora.test/aziende/'.$company->slug)
            ->assertStatus(301)
            ->assertRedirect('http://caseificioaurora.test');
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super amministratore', 'is_system' => true]);

        return User::create([
            'name' => 'Amministratore', 'email' => 'admin'.uniqid().'@example.test', 'password' => 'password',
            'user_type' => 'admin', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }
}
