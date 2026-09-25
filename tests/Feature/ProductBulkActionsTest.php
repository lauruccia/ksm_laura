<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Azioni su piu' prodotti insieme, in amministrazione e nell'area azienda.
 *
 * Le righe spuntate o tutti i risultati della ricerca: nel secondo caso
 * il server rifa' la ricerca con gli stessi filtri, e il venditore resta
 * sempre dentro i suoi prodotti.
 */
class ProductBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super amministratore', 'is_system' => true]);

        return User::create([
            'name' => 'Amministratore', 'email' => 'admin'.uniqid().'@example.test', 'password' => 'password',
            'user_type' => 'admin', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function company(string $name): Company
    {
        $plan = Plan::firstOrCreate(['slug' => 'ecommerce'], [
            'name' => 'Ecommerce', 'price' => 349, 'priority' => 40, 'duration_days' => 365, 'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOP],
        ]);
        $owner = User::create(['name' => $name, 'email' => str($name)->slug().'@example.test', 'password' => 'password', 'user_type' => 'vendor']);

        return Company::create(['user_id' => $owner->id, 'plan_id' => $plan->id, 'name' => $name, 'slug' => str($name)->slug(), 'is_active' => true]);
    }

    private function product(Company $company, string $name, string $status = 'active'): Product
    {
        return Product::create([
            'company_id' => $company->id, 'name' => $name, 'slug' => str($name)->slug().'-'.uniqid(),
            'price' => 10, 'product_type' => 'simple', 'status' => $status,
        ]);
    }

    public function test_l_amministratore_disattiva_le_righe_spuntate(): void
    {
        $company = $this->company('Caseificio');
        [$a, $b, $c] = [$this->product($company, 'Mozzarella'), $this->product($company, 'Ricotta'), $this->product($company, 'Burrata')];

        $this->actingAs($this->admin())
            ->patch(route('admin.products.bulk'), ['action' => 'deactivate', 'ids' => [$a->id, $b->id]])
            ->assertSessionHas('success', '2 prodotti disattivati.');

        $this->assertSame(['inactive', 'inactive', 'active'], [$a->fresh()->status, $b->fresh()->status, $c->fresh()->status]);
    }

    public function test_tutti_i_risultati_rifanno_la_ricerca_con_gli_stessi_filtri(): void
    {
        $company = $this->company('Caseificio');
        $other = $this->company('Salumificio');
        $cheeses = collect(range(1, 30))->map(fn ($i) => $this->product($company, "Formaggio $i", 'inactive'));
        $ham = $this->product($other, 'Prosciutto', 'inactive');

        // La pagina mostra 25 righe, ma "tutti i risultati" le prende tutte e 30.
        $this->actingAs($this->admin())
            ->get(route('admin.products.index', ['cerca' => 'Formaggio']))
            ->assertSee('Tutti i risultati (30)');

        $this->patch(route('admin.products.bulk'), ['action' => 'activate', 'scope' => 'all', 'cerca' => 'Formaggio'])
            ->assertSessionHas('success', '30 prodotti attivati.');

        $this->assertSame(30, Product::whereKey($cheeses->pluck('id'))->where('status', 'active')->count());
        $this->assertSame('inactive', $ham->fresh()->status);
    }

    public function test_senza_righe_scelte_non_succede_niente(): void
    {
        $company = $this->company('Caseificio');
        $product = $this->product($company, 'Mozzarella');

        $this->actingAs($this->admin())
            ->patch(route('admin.products.bulk'), ['action' => 'delete'])
            ->assertSessionHasErrors('ids');

        $this->assertModelExists($product);
    }

    public function test_l_amministratore_elimina_e_cambia_la_quota_kmoney(): void
    {
        $company = $this->company('Caseificio');
        [$a, $b] = [$this->product($company, 'Mozzarella'), $this->product($company, 'Ricotta')];

        $this->actingAs($this->admin())
            ->patch(route('admin.products.bulk'), ['action' => 'kmoney', 'percent' => 50, 'ids' => [$a->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame(50, (int) $a->fresh()->kmoney_discount_percent);

        $this->patch(route('admin.products.bulk'), ['action' => 'delete', 'ids' => [$b->id]])
            ->assertSessionHas('success', '1 prodotto eliminato.');
        $this->assertModelMissing($b);
    }

    public function test_il_venditore_agisce_solo_sui_suoi_prodotti(): void
    {
        $mine = $this->company('Caseificio');
        $theirs = $this->company('Salumificio');
        $cheese = $this->product($mine, 'Mozzarella');
        $ham = $this->product($theirs, 'Prosciutto');

        $vendor = $mine->user;

        $this->actingAs($vendor)
            ->patch(route('vendor.products.bulk'), ['action' => 'deactivate', 'ids' => [$cheese->id, $ham->id]])
            ->assertSessionHas('success', '1 prodotto disattivato.');

        $this->patch(route('vendor.products.bulk'), ['action' => 'delete', 'scope' => 'all'])
            ->assertSessionHas('success', '1 prodotto eliminato.');

        $this->assertModelMissing($cheese);
        $this->assertSame('active', $ham->fresh()->status);
        $this->assertModelExists($ham);
    }
}
