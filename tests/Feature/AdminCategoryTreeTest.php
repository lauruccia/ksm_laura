<?php

namespace Tests\Feature;

use App\Models\CompanyCategory;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use App\Support\Ads\AdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Categorie su piu' livelli in amministrazione.
 *
 * Il sito originale ha categorie aziende al terzo livello: il modulo deve
 * mostrarne la madre e salvarle senza staccarle dal ramo.
 */
class AdminCategoryTreeTest extends TestCase
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

    /** @return array{0: CompanyCategory, 1: CompanyCategory, 2: CompanyCategory} */
    private function branch(): array
    {
        $root = CompanyCategory::create(['name' => 'Edilizia', 'slug' => 'edilizia']);
        $middle = CompanyCategory::create(['name' => 'Imprese Edili', 'slug' => 'imprese-edili', 'parent_id' => $root->id]);
        $leaf = CompanyCategory::create(['name' => 'Amianto', 'slug' => 'amianto', 'parent_id' => $middle->id]);

        return [$root, $middle, $leaf];
    }

    public function test_la_categoria_al_terzo_livello_ritrova_la_sua_madre_nel_modulo(): void
    {
        [, $middle, $leaf] = $this->branch();

        $this->actingAs($this->admin())
            ->get(route('admin.company_categories.edit', $leaf))
            ->assertOk()
            ->assertSee('<option value="'.$middle->id.'" selected>Edilizia › Imprese Edili</option>', false);
    }

    public function test_salvare_la_categoria_al_terzo_livello_la_lascia_nel_suo_ramo(): void
    {
        [, $middle, $leaf] = $this->branch();

        $this->actingAs($this->admin())
            ->put(route('admin.company_categories.update', $leaf), [
                'name' => 'Amianto',
                'slug' => 'amianto',
                'parent_id' => $middle->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($middle->id, $leaf->fresh()->parent_id);
    }

    public function test_una_categoria_non_puo_finire_dentro_il_suo_ramo(): void
    {
        [$root, , $leaf] = $this->branch();

        $this->actingAs($this->admin())
            ->get(route('admin.company_categories.edit', $root))
            ->assertDontSee('Edilizia › Imprese Edili</option>', false);

        $this->put(route('admin.company_categories.update', $root), [
            'name' => 'Edilizia',
            'slug' => 'edilizia',
            'parent_id' => $leaf->id,
        ])->assertSessionHasErrors('parent_id');

        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_le_categorie_aziende_si_fermano_al_terzo_livello(): void
    {
        [$root, , $leaf] = $this->branch();
        $other = CompanyCategory::create(['name' => 'Altro', 'slug' => 'altro']);

        $this->actingAs($this->admin())
            ->post(route('admin.company_categories.store'), ['name' => 'Quarto livello', 'parent_id' => $leaf->id])
            ->assertSessionHasErrors('parent_id');

        // Spostare un ramo di tre livelli sotto un'altra categoria ne farebbe quattro.
        $this->put(route('admin.company_categories.update', $root), [
            'name' => 'Edilizia',
            'slug' => 'edilizia',
            'parent_id' => $other->id,
        ])->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('company_categories', ['name' => 'Quarto livello']);
    }

    public function test_le_categorie_prodotti_restano_a_due_livelli(): void
    {
        $food = ProductCategory::create(['name' => 'Alimentari', 'slug' => 'alimentari']);
        $typical = ProductCategory::create(['name' => 'Tipici', 'slug' => 'tipici', 'parent_id' => $food->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.product_categories.store'), ['name' => 'Terzo livello', 'parent_id' => $typical->id])
            ->assertSessionHasErrors('parent_id');

        $this->post(route('admin.product_categories.store'), ['name' => 'Formaggi', 'parent_id' => $food->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_categories', ['name' => 'Formaggi', 'slug' => 'formaggi', 'parent_id' => $food->id]);
    }

    public function test_l_elenco_mostra_categoria_superiore_e_livello(): void
    {
        $this->branch();

        $this->actingAs($this->admin())
            ->get(route('admin.company_categories.index', ['cerca' => 'Amianto']))
            ->assertOk()
            ->assertSeeInOrder(['Categoria superiore', 'Livello', 'Amianto', 'Edilizia › Imprese Edili', '3']);
    }

    public function test_i_banner_di_una_categoria_principale_arrivano_al_terzo_livello(): void
    {
        [$root, $middle, $leaf] = $this->branch();

        $this->assertSame(
            [$leaf->id, $middle->id, $root->id],
            AdContext::current(categoryId: $leaf->id)->categoryIds
        );
    }
}
