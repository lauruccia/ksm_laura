<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menu del sito principale scritti in Amministrazione, uno per posizione.
 */
class SiteMenuTest extends TestCase
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

    public function test_senza_menu_scritti_il_sito_resta_com_era(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('brand-header__top', $html);
        $this->assertMatchesRegularExpression('/brand-header__nav--left.*Home.*Aziende.*Prodotti.*Piani/s', $html);
    }

    public function test_la_pagina_propone_le_voci_predefinite(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.menus.edit'))
            ->assertOk()
            ->assertSee('Barra in alto · a sinistra')
            ->assertSee('Voci predefinite')
            ->assertSee('value="/piani"', false);
    }

    public function test_ogni_posizione_si_salva_da_sola_e_compare_nel_sito(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.menus.update', 'top_right'), [
            'items' => [
                ['label' => 'Assistenza', 'url' => '/contatti'],
                ['label' => '', 'url' => ''],
                ['label' => 'Blog', 'url' => 'https://blog.example.test', 'new_tab' => '1'],
            ],
        ])->assertRedirect();

        $this->actingAs($admin)->put(route('admin.menus.update', 'header_left'), [
            'items' => [['label' => 'Vetrine', 'url' => '/aziende']],
        ])->assertRedirect();

        $this->assertSame([
            'top_right' => [
                ['label' => 'Assistenza', 'url' => '/contatti', 'new_tab' => false],
                ['label' => 'Blog', 'url' => 'https://blog.example.test', 'new_tab' => true],
            ],
            'header_left' => [['label' => 'Vetrine', 'url' => '/aziende', 'new_tab' => false]],
        ], AdminSetting::current()->menus);

        $html = $this->get(route('companies.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/brand-header__top-nav--right.*Assistenza.*href="https:\/\/blog\.example\.test"\s+target="_blank"/s', $html);
        $this->assertMatchesRegularExpression('/href="\/aziende" class="brand-header__link\s+is-active\s*"/', $html);
        // Il menu principale scritto sostituisce quello predefinito.
        preg_match('/brand-header__nav--left.*?<\/nav>/s', $html, $nav);
        $this->assertStringContainsString('Vetrine', $nav[0]);
        $this->assertStringNotContainsString('Piani', $nav[0]);
    }

    public function test_ripristina_torna_alle_voci_predefinite(): void
    {
        AdminSetting::current()->update(['menus' => ['footer_links' => [['label' => 'Solo questa', 'url' => '/']]]]);

        $this->get(route('home'))->assertSee('Solo questa');

        $this->actingAs($this->admin())
            ->put(route('admin.menus.update', 'footer_links'), ['reset' => '1'])
            ->assertRedirect();

        $this->assertSame([], AdminSetting::current()->menus);
        $this->get(route('home'))->assertDontSee('Solo questa');
    }

    public function test_i_link_pericolosi_e_le_voci_a_meta_non_passano(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.menus.update', 'top_left'), [
            'items' => [['label' => 'X', 'url' => 'javascript:alert(1)']],
        ])->assertSessionHasErrors('items.0.url');

        $this->actingAs($admin)->put(route('admin.menus.update', 'top_left'), [
            'items' => [['label' => '', 'url' => '/prodotti']],
        ])->assertSessionHasErrors('items.0.label');

        $this->actingAs($admin)->put(route('admin.menus.update', 'nessuna'), ['items' => []])->assertNotFound();

        $this->assertNull(AdminSetting::current()->menus);
    }
}
