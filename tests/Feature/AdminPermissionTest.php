<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * I permessi dei ruoli, visti dalle rotte.
 *
 * Non basta che il Gate risponda giusto: quello che conta e' che una
 * pagina non concessa risponda 403 anche a chi ne conosce l'indirizzo.
 */
class AdminPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_il_ruolo_di_sistema_apre_tutto(): void
    {
        $admin = $this->admin(Role::where('slug', Role::SUPER_ADMIN)->firstOrFail());

        foreach ([
            route('admin.dashboard'),
            route('admin.users.index'),
            route('admin.roles.index'),
            route('admin.companies.index'),
            route('admin.company_categories.index'),
            route('admin.products.index'),
            route('admin.product_categories.index'),
            route('admin.brands.index'),
            route('admin.orders.index'),
            route('admin.payments.index'),
            route('admin.plans.index'),
            route('admin.subscriptions.index'),
            route('admin.cms.index'),
            route('admin.advertisements.index'),
            route('admin.domains.index'),
            route('admin.settings.edit'),
            route('admin.profile.edit'),
            route('admin.users.create'),
            route('admin.roles.create'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_un_ruolo_ristretto_vede_solo_cio_che_gli_compete(): void
    {
        $role = Role::create([
            'name' => 'Assistenza',
            'slug' => 'assistenza-test',
            'permissions' => [Permissions::DASHBOARD_VIEW, Permissions::ORDERS_VIEW],
        ]);

        $user = $this->admin($role);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('admin.orders.index'))->assertOk();

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_vedere_gli_ordini_non_basta_per_cambiarli(): void
    {
        $role = Role::create([
            'name' => 'Sola lettura',
            'slug' => 'sola-lettura-test',
            'permissions' => [Permissions::ORDERS_VIEW],
        ]);

        $order = $this->order();

        $this->actingAs($this->admin($role))
            ->patch(route('admin.orders.status', $order), ['status' => 'paid'])
            ->assertForbidden();

        $this->assertSame('pending', $order->fresh()->status);
    }

    /** Un ordine vero: la rotta risolve il modello prima di guardare i permessi. */
    private function order(): Order
    {
        $company = Company::create([
            'user_id' => User::factory()->create(['user_type' => 'vendor'])->id,
            'name' => 'Azienda di prova',
            'slug' => 'azienda-di-prova',
            'city' => 'Roma',
            'is_active' => true,
        ]);

        return Order::create([
            'user_id' => User::factory()->create()->id,
            'company_id' => $company->id,
            'subtotal' => 10,
            'total' => 10,
            'currency' => 'EUR',
            'status' => 'pending',
            'billing_name' => 'Cliente',
            'billing_email' => 'cliente@example.test',
        ]);
    }

    public function test_senza_ruolo_il_pannello_resta_chiuso(): void
    {
        $user = User::factory()->create(['user_type' => 'admin', 'role_id' => null]);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_un_accesso_sospeso_non_entra(): void
    {
        $user = $this->admin(Role::where('slug', Role::SUPER_ADMIN)->firstOrFail());
        $user->update(['is_active' => false]);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_chi_non_ha_il_ruolo_di_sistema_non_puo_assegnarlo(): void
    {
        $role = Role::create([
            'name' => 'Gestore utenti',
            'slug' => 'gestore-utenti-test',
            'permissions' => [Permissions::USERS_VIEW, Permissions::USERS_MANAGE],
        ]);

        $system = Role::where('slug', Role::SUPER_ADMIN)->firstOrFail();

        $this->actingAs($this->admin($role))
            ->post(route('admin.users.store'), [
                'name' => 'Nuovo',
                'email' => 'nuovo@example.test',
                'user_type' => 'admin',
                'role_id' => $system->id,
                'password' => 'password-lunga-1',
                'password_confirmation' => 'password-lunga-1',
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'nuovo@example.test']);
    }

    public function test_nessuno_si_toglie_da_solo_l_amministrazione(): void
    {
        $admin = $this->admin(Role::where('slug', Role::SUPER_ADMIN)->firstOrFail());

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'user_type' => 'buyer',
            'role_id' => null,
            'is_active' => 0,
        ])->assertRedirect();

        $admin->refresh();

        $this->assertSame('admin', $admin->user_type);
        $this->assertTrue($admin->is_active);
    }

    private function admin(Role $role): User
    {
        return User::factory()->create([
            'user_type' => 'admin',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
