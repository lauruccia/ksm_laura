<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use App\Support\PlanCapabilities;
use App\Support\Sites\SiteScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Il filtro di un dominio a blocchi indipendenti: aziende, prodotti, luogo.
 */
class DomainFilterBlocksTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://vetrina.test';

    private Plan $plan;

    private CompanyCategory $restaurants;

    private CompanyCategory $pizzerie;

    private CompanyCategory $hotels;

    private CompanyCategory $shops;

    private ProductCategory $voucher;

    private ProductCategory $wine;

    private ProductCategory $shoes;

    private Company $trattoria;

    private Company $pizzeria;

    private Company $albergo;

    private Company $negozio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::create([
            'name' => 'Piano completo', 'slug' => 'completo', 'price' => 99, 'priority' => 20,
            'duration_days' => 365, 'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOWCASE, PlanCapabilities::SHOP],
        ]);

        $this->restaurants = CompanyCategory::create(['name' => 'Ristoranti', 'slug' => 'ristoranti']);
        $this->pizzerie = CompanyCategory::create(['name' => 'Pizzerie', 'slug' => 'pizzerie', 'parent_id' => $this->restaurants->id]);
        $this->hotels = CompanyCategory::create(['name' => 'Alberghi', 'slug' => 'alberghi']);
        $this->shops = CompanyCategory::create(['name' => 'Negozi', 'slug' => 'negozi']);

        $this->voucher = ProductCategory::create(['name' => 'Voucher', 'slug' => 'voucher']);
        $this->wine = ProductCategory::create(['name' => 'Vini', 'slug' => 'vini']);
        $this->shoes = ProductCategory::create(['name' => 'Scarpe', 'slug' => 'scarpe']);

        $this->trattoria = $this->company('Trattoria Tropea', $this->restaurants);
        $this->pizzeria = $this->company('Pizzeria Cosenza', $this->pizzerie);
        $this->albergo = $this->company('Albergo Scilla', $this->hotels);
        $this->negozio = $this->company('Calzature Rossi', $this->shops);

        $this->product($this->trattoria, 'Voucher cena per due', $this->voucher);
        $this->product($this->trattoria, 'Rosso della casa', $this->wine);
        $this->product($this->albergo, 'Voucher notte al mare', $this->voucher);
        $this->product($this->negozio, 'Stivale in pelle', $this->shoes);
        $this->product($this->negozio, 'Voucher scarpe', $this->voucher);
    }

    private function company(string $name, CompanyCategory $category): Company
    {
        $user = User::create(['name' => $name, 'email' => uniqid().'@example.test', 'password' => 'password', 'user_type' => 'vendor']);

        return Company::create([
            'user_id' => $user->id, 'plan_id' => $this->plan->id, 'category_id' => $category->id,
            'name' => $name, 'slug' => Str::slug($name), 'is_active' => true,
        ]);
    }

    private function product(Company $company, string $name, ProductCategory $category): Product
    {
        return Product::create([
            'company_id' => $company->id, 'category_id' => $category->id, 'name' => $name,
            'slug' => Str::slug($name), 'price' => 14.9, 'stock' => 5, 'status' => 'active',
        ]);
    }

    private function domain(array $attributes = []): Domain
    {
        return Domain::create($attributes + ['name' => 'Vetrina', 'domain' => 'vetrina.test', 'entry_page' => 'home', 'is_active' => true]);
    }

    /** @return list<string> */
    private function companies(Domain $domain): array
    {
        return (new SiteScope($domain))->companies(Company::query())->orderBy('name')->pluck('name')->all();
    }

    /** @return list<string> */
    private function products(Domain $domain): array
    {
        return (new SiteScope($domain))->products(Product::query())->orderBy('name')->pluck('name')->all();
    }

    public function test_piu_categorie_aziende_con_i_soli_prodotti_di_quelle_aziende(): void
    {
        $domain = $this->domain(['company_category_ids' => [$this->restaurants->id, $this->hotels->id]]);

        // Le sottocategorie entrano con la madre; le aziende senza prodotti restano.
        $this->assertSame(['Albergo Scilla', 'Pizzeria Cosenza', 'Trattoria Tropea'], $this->companies($domain));
        $this->assertSame(['Rosso della casa', 'Voucher cena per due', 'Voucher notte al mare'], $this->products($domain));

        $this->get(self::HOST.'/aziende')->assertOk()->assertSee('Albergo Scilla')->assertSee('Trattoria Tropea')->assertDontSee('Calzature Rossi');
        $this->get(self::HOST.'/prodotti')->assertOk()->assertSee('Voucher notte al mare')->assertDontSee('Stivale in pelle');
    }

    public function test_categorie_aziende_con_i_prodotti_di_tutto_il_marketplace(): void
    {
        $domain = $this->domain(['company_category_ids' => [$this->restaurants->id], 'product_scope' => 'all']);

        $this->assertSame(['Pizzeria Cosenza', 'Trattoria Tropea'], $this->companies($domain));
        $this->assertSame(
            ['Rosso della casa', 'Stivale in pelle', 'Voucher cena per due', 'Voucher notte al mare', 'Voucher scarpe'],
            $this->products($domain)
        );

        // Con le categorie prodotti: di tutto il marketplace, ma solo quelle categorie.
        $domain->update(['product_category_ids' => [$this->voucher->id, $this->shoes->id]]);
        $this->assertSame(
            ['Stivale in pelle', 'Voucher cena per due', 'Voucher notte al mare', 'Voucher scarpe'],
            $this->products($domain->fresh())
        );
    }

    public function test_senza_prodotti_il_sito_e_solo_una_vetrina_di_aziende(): void
    {
        $domain = $this->domain(['company_category_ids' => [$this->restaurants->id], 'product_scope' => 'none']);
        $voucher = Product::where('name', 'Voucher cena per due')->firstOrFail();

        $this->assertSame([], $this->products($domain));
        $this->assertSame(['Pizzeria Cosenza', 'Trattoria Tropea'], $this->companies($domain));
        $this->assertFalse((new SiteScope($domain))->hasProducts());

        $this->get(self::HOST.'/aziende')->assertOk()->assertSee('Trattoria Tropea');
        $this->get(self::HOST.'/prodotti/'.$voucher->slug)->assertNotFound();
        $this->post(self::HOST.'/carrello/aggiungi/'.$voucher->slug)->assertNotFound();
    }

    public function test_piu_categorie_prodotti_e_solo_le_aziende_che_le_vendono(): void
    {
        $domain = $this->domain([
            'product_category_ids' => [$this->wine->id, $this->shoes->id], 'companies_with_products' => true,
        ]);

        $this->assertSame(['Calzature Rossi', 'Trattoria Tropea'], $this->companies($domain));
        $this->assertSame(['Rosso della casa', 'Stivale in pelle'], $this->products($domain));

        // Spenta la richiesta, le categorie prodotti non toccano le aziende.
        $domain->update(['companies_with_products' => false]);
        $this->assertCount(4, $this->companies($domain->fresh()));
    }

    public function test_categoria_azienda_e_categoria_prodotto_diverse_con_il_luogo(): void
    {
        $this->trattoria->update(['city' => 'Tropea', 'region' => 'Calabria']);
        $this->pizzeria->update(['city' => 'Roma', 'region' => 'Lazio']);
        $this->albergo->update(['city' => 'Scilla', 'region' => 'Calabria']);

        $domain = $this->domain([
            'company_category_ids' => [$this->restaurants->id], 'product_category_ids' => [$this->voucher->id], 'filter_place' => ' calabria ',
        ]);

        $this->assertSame('category+city', $domain->type);
        $this->assertSame('calabria', $domain->filter_place);
        $this->assertSame(['Trattoria Tropea'], $this->companies($domain));
        $this->assertSame(['Voucher cena per due'], $this->products($domain));
    }

    public function test_senza_scelte_il_dominio_mostra_tutto(): void
    {
        $domain = $this->domain();

        $this->assertSame('home', $domain->type);
        $this->assertFalse((new SiteScope($domain))->isRestricted());
        $this->assertCount(4, $this->companies($domain));
        $this->assertCount(5, $this->products($domain));
    }

    public function test_una_categoria_cancellata_non_filtra_piu(): void
    {
        $domain = $this->domain(['company_category_ids' => [$this->hotels->id, 9999]]);
        $this->assertSame(['Albergo Scilla'], $this->companies($domain));

        $domain->update(['company_category_ids' => [9999]]);
        $this->assertCount(4, $this->companies($domain->fresh()));
    }

    public function test_il_modulo_salva_i_tre_blocchi_e_le_spunte_tolte(): void
    {
        $role = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super amministratore', 'is_system' => true]);
        $admin = User::create([
            'name' => 'Amministratore', 'email' => 'admin@example.test', 'password' => 'password',
            'user_type' => 'admin', 'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.domains.create'))
            ->assertOk()
            ->assertSee('name="company_category_ids[]"', false)
            ->assertSee('name="product_category_ids[]"', false)
            ->assertSee('Di tutto il marketplace')
            ->assertSee('Nessun prodotto')
            ->assertDontSee('name="company_scope"', false)
            ->assertDontSee('name="type"', false);

        $this->actingAs($admin)->post(route('admin.domains.store'), [
            'name' => 'Vetrina', 'domain' => 'vetrina.test', 'is_active' => '1', 'entry_page' => 'home',
            'company_category_ids' => [$this->restaurants->id, $this->hotels->id],
            'companies_with_products' => '1',
            'product_scope' => 'all',
            'product_category_ids' => [$this->voucher->id, $this->wine->id],
            'filter_place' => 'Calabria',
            'city' => 'Reggio Calabria',
        ])->assertSessionHasNoErrors();

        $domain = Domain::where('domain', 'vetrina.test')->firstOrFail();
        $this->assertSame([$this->restaurants->id, $this->hotels->id], $domain->company_category_ids);
        $this->assertSame([$this->voucher->id, $this->wine->id], $domain->product_category_ids);
        $this->assertTrue($domain->companies_with_products);
        $this->assertSame('all', $domain->product_scope);
        $this->assertSame('Calabria', $domain->filter_place);
        // La citta' dei recapiti non filtra piu' nulla.
        $this->assertSame('Reggio Calabria', $domain->city);
        $this->assertSame('category+city', $domain->type);

        $this->actingAs($admin)->get(route('admin.domains.edit', $domain))
            ->assertOk()
            ->assertSee('name="company_category_ids[]" value="'.$this->hotels->id.'" data-label="Alberghi" checked', false);

        // Tolte tutte le spunte, le categorie si svuotano invece di restare com'erano.
        $this->actingAs($admin)->put(route('admin.domains.update', $domain), [
            'name' => 'Vetrina', 'domain' => 'vetrina.test', 'is_active' => '1', 'entry_page' => 'home',
            'companies_with_products' => '0', 'product_scope' => 'none', 'filter_place' => '',
        ])->assertSessionHasNoErrors();

        $domain->refresh();
        $this->assertNull($domain->company_category_ids);
        $this->assertNull($domain->product_category_ids);
        $this->assertFalse($domain->companies_with_products);
        $this->assertSame('none', $domain->product_scope);
        $this->assertNull($domain->filter_place);
        $this->assertSame('home', $domain->type);

        $this->actingAs($admin)->put(route('admin.domains.update', $domain), [
            'name' => 'Vetrina', 'domain' => 'vetrina.test', 'entry_page' => 'home',
            'product_scope' => 'tutto', 'company_category_ids' => [9999],
        ])->assertSessionHasErrors(['product_scope', 'company_category_ids.0']);
    }

    public function test_i_domini_di_prima_si_convertono_con_lo_stesso_risultato(): void
    {
        $legacy = fn (array $row) => Domain::filterFromLegacy($row + [
            'type' => 'category', 'company_category_id' => 7, 'product_category_id' => 3, 'company_scope' => 'category', 'city' => 'Ostia',
        ]);

        // Per categoria: il luogo non contava, anche se la citta' dei recapiti era compilata.
        $this->assertSame(
            ['company_category_ids' => [7], 'product_category_ids' => [3], 'companies_with_products' => false, 'product_scope' => 'companies', 'filter_place' => null],
            $legacy([])
        );

        // "Solo chi vende": la categoria azienda non contava.
        $this->assertSame([], $legacy(['company_scope' => 'products'])['company_category_ids']);
        $this->assertTrue($legacy(['company_scope' => 'products'])['companies_with_products']);
        $this->assertSame([7], $legacy(['company_scope' => 'both'])['company_category_ids']);
        $this->assertTrue($legacy(['company_scope' => 'both'])['companies_with_products']);

        // Per citta': le categorie non contavano.
        $this->assertSame(
            ['company_category_ids' => [], 'product_category_ids' => [], 'companies_with_products' => false, 'product_scope' => 'companies', 'filter_place' => 'Ostia'],
            $legacy(['type' => 'city'])
        );
        $this->assertSame('Ostia', $legacy(['type' => 'category+city'])['filter_place']);
        $this->assertSame([3], $legacy(['type' => 'category+city'])['product_category_ids']);

        // Nessun filtro: tutto vuoto.
        $this->assertSame([], $legacy(['type' => 'home'])['product_category_ids']);
        $this->assertNull($legacy(['type' => 'home'])['filter_place']);
    }
}
