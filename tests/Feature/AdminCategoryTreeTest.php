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

    public function test_l_elenco_e_l_albero_con_le_aziende_di_ogni_ramo(): void
    {
        [$root, $middle, $leaf] = $this->branch();
        $owner = User::create(['name' => 'Titolare', 'email' => 'titolare@example.test', 'password' => 'password', 'user_type' => 'vendor']);
        \App\Models\Company::create(['user_id' => $owner->id, 'name' => 'Bonifiche Srl', 'slug' => 'bonifiche', 'category_id' => $leaf->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.company_categories.index'))
            ->assertOk()
            ->assertSeeInOrder(['Edilizia', 'Imprese Edili', 'Amianto'])
            ->assertSee('1 azienda')
            ->assertSee('value="'.$root->id.'">Edilizia</option>', false)
            // Al terzo livello non si aggiungono altre sottocategorie.
            ->assertSee('id="sub-'.$middle->id.'"', false)
            ->assertDontSee('id="sub-'.$leaf->id.'"', false);
    }

    public function test_la_ricerca_tiene_le_madri_della_categoria_trovata(): void
    {
        $this->branch();
        CompanyCategory::create(['name' => 'Ristoranti', 'slug' => 'ristoranti']);

        $this->actingAs($this->admin())
            ->get(route('admin.company_categories.index', ['cerca' => 'amianto']))
            ->assertOk()
            ->assertSeeInOrder(['Edilizia', 'Imprese Edili', 'Amianto'])
            ->assertDontSee('ristoranti');
    }

    public function test_la_sottocategoria_nasce_dalla_riga_della_madre(): void
    {
        [, $middle] = $this->branch();

        $this->actingAs($this->admin())
            ->get(route('admin.company_categories.create', ['madre' => $middle->id]))
            ->assertSee('<option value="'.$middle->id.'" selected>Edilizia › Imprese Edili</option>', false);

        $response = $this->post(route('admin.company_categories.store'), ['name' => 'Cartongesso', 'parent_id' => $middle->id]);

        $created = CompanyCategory::where('name', 'Cartongesso')->firstOrFail();
        $response->assertRedirect(route('admin.company_categories.index').'#categoria-'.$created->id);
        $this->assertSame($middle->id, $created->parent_id);
        $this->assertSame('cartongesso', $created->slug);
    }

    public function test_eliminare_una_categoria_non_elimina_il_suo_ramo(): void
    {
        [$root, $middle, $leaf] = $this->branch();
        $owner = User::create(['name' => 'Titolare', 'email' => 'titolare@example.test', 'password' => 'password', 'user_type' => 'vendor']);
        $company = \App\Models\Company::create(['user_id' => $owner->id, 'name' => 'Impresa Rossi', 'slug' => 'impresa-rossi', 'category_id' => $middle->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.company_categories.destroy', $middle))
            ->assertRedirect(route('admin.company_categories.index'));

        $this->assertDatabaseMissing('company_categories', ['id' => $middle->id]);
        $this->assertSame($root->id, $leaf->fresh()->parent_id);
        $this->assertSame($root->id, $company->fresh()->category_id);
    }

    public function test_l_icona_scelta_vale_sui_biglietti(): void
    {
        [$root, , $leaf] = $this->branch();

        $this->actingAs($this->admin())
            ->put(route('admin.company_categories.update', $root), ['name' => 'Edilizia', 'slug' => 'edilizia', 'icon' => 'truck'])
            ->assertSessionHasNoErrors();

        $this->assertSame('truck', \App\Support\CategoryIcon::for($leaf->fresh()->load('parent.parent')));

        $this->put(route('admin.company_categories.update', $root), ['name' => 'Edilizia', 'slug' => 'edilizia', 'icon' => 'bomba'])
            ->assertSessionHasErrors('icon');
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
