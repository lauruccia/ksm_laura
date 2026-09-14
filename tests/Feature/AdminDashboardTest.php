<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $type, array $extra = []): User
    {
        return User::create([
            'name' => ucfirst($type),
            'email' => $type.uniqid().'@example.test',
            'password' => 'password',
            'user_type' => $type,
            'is_active' => true,
        ] + $extra);
    }

    public function test_la_scheda_utenti_non_conta_gli_accessi_delle_aziende(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => Role::SUPER_ADMIN],
            ['name' => 'Super amministratore', 'is_system' => true]
        );
        $admin = $this->user('admin', ['role_id' => $role->id]);
        $this->user('vendor');
        $this->user('vendor');
        $this->user('buyer');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('cards', function (array $cards) {
                $users = collect($cards)->firstWhere('label', 'Utenti');

                return $users['value'] === '2' && $users['note'] === '1 in amministrazione, 1 cliente';
            });
    }
}
