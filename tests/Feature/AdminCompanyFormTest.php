<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Payments\Subscriptions\SubscriptionActivator;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * La scheda azienda in amministrazione.
 *
 * Ha le voci della scheda del sito originale. Qui si prova che ognuna
 * arrivi dove deve: l'accesso sull'utente titolare, il piano sugli
 * abbonamenti, le foto sul disco, e solo quelle della scheda.
 */
class AdminCompanyFormTest extends TestCase
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

    private function plan(string $name, ?int $duration = 365): Plan
    {
        return Plan::create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'price' => 240,
            'priority' => 10,
            'duration_days' => $duration,
            'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY],
        ]);
    }

    private function company(array $attributes = []): Company
    {
        $owner = User::create([
            'name' => 'Titolare',
            'email' => 'titolare'.uniqid().'@example.test',
            'password' => 'vecchia-password',
            'user_type' => 'vendor',
        ]);

        return Company::create($attributes + [
            'user_id' => $owner->id,
            'name' => 'Decina Bus',
            'slug' => 'decina-bus-'.uniqid(),
            'email' => 'info@decina.test',
            'is_active' => true,
        ]);
    }

    /** I campi obbligatori con i valori attuali, piu' quelli da cambiare. */
    private function payload(Company $company, array $overrides = []): array
    {
        return $overrides + [
            'name' => $company->name,
            'login_email' => $company->user->email,
            'plan_id' => $company->plan_id,
            'email' => $company->email,
            'is_active' => '1',
        ];
    }

    public function test_la_scheda_mostra_l_accesso_del_titolare(): void
    {
        $company = $this->company();

        $this->actingAs($this->admin())
            ->get(route('admin.companies.edit', $company))
            ->assertOk()
            ->assertSee($company->user->email)
            ->assertSee('Orari di apertura');
    }

    public function test_l_amministratore_cambia_email_e_password_di_accesso(): void
    {
        $company = $this->company();

        $this->actingAs($this->admin())
            ->put(route('admin.companies.update', $company), $this->payload($company, [
                'login_email' => 'nuovo@decina.test',
                'password' => 'NuovaPassword2026!',
                'password_confirmation' => 'NuovaPassword2026!',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $owner = $company->user->fresh();

        $this->assertSame('nuovo@decina.test', $owner->email);
        $this->assertTrue(Hash::check('NuovaPassword2026!', $owner->password));
    }

    public function test_senza_nuova_password_resta_quella_di_prima(): void
    {
        $company = $this->company();

        $this->actingAs($this->admin())
            ->put(route('admin.companies.update', $company), $this->payload($company, ['phone' => '06 123']))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('vecchia-password', $company->user->fresh()->password));
        $this->assertSame('06 123', $company->fresh()->phone);
    }

    public function test_scegliere_un_piano_apre_un_abbonamento_attivo(): void
    {
        $company = $this->company();
        $plan = $this->plan('Anagrafica');

        $this->actingAs($this->admin())
            ->put(route('admin.companies.update', $company), $this->payload($company, ['plan_id' => $plan->id]))
            ->assertSessionHasNoErrors();

        $company->refresh();
        $subscription = $company->activeSubscription();

        $this->assertEquals($plan->id, $company->plan_id);
        $this->assertNotNull($subscription);
        $this->assertSame('admin', $subscription->payment_method);
        $this->assertTrue($subscription->ends_at->isFuture());
    }

    public function test_togliere_il_piano_chiude_l_abbonamento(): void
    {
        $company = $this->company();
        app(SubscriptionActivator::class)->assign($company, $this->plan('Vetrina', null));
        $company->refresh();

        $this->actingAs($this->admin())
            ->put(route('admin.companies.update', $company), $this->payload($company, ['plan_id' => null]))
            ->assertSessionHasNoErrors();

        $company->refresh();

        $this->assertNull($company->plan_id);
        $this->assertNull($company->activeSubscription());
    }

    public function test_gli_orari_si_salvano_e_i_giorni_vuoti_restano_chiusi(): void
    {
        $company = $this->company();

        $this->actingAs($this->admin())
            ->put(route('admin.companies.update', $company), $this->payload($company, [
                'working_hours' => [
                    'Monday' => ['start' => '09:00', 'end' => '18:00'],
                    'Sunday' => ['start' => '', 'end' => ''],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['Monday' => ['start' => '09:00', 'end' => '18:00']], $company->fresh()->working_hours);
    }

    public function test_una_chiusura_prima_dell_apertura_viene_respinta(): void
    {
        $company = $this->company();

        $this->actingAs($this->admin())
            ->put(route('admin.companies.update', $company), $this->payload($company, [
                'working_hours' => ['Monday' => ['start' => '18:00', 'end' => '09:00']],
            ]))
            ->assertSessionHasErrors('working_hours.Monday.end');
    }

    public function test_la_galleria_aggiunge_e_toglie_foto(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('companies/vecchia.jpg', 'x');
        $company = $this->company(['offer_gallery' => ['companies/vecchia.jpg']]);

        $this->actingAs($this->admin())
            ->put(route('admin.companies.update', $company), $this->payload($company, [
                'remove_gallery' => ['companies/vecchia.jpg'],
                'gallery' => [UploadedFile::fake()->image('bus.jpg')],
            ]))
            ->assertSessionHasNoErrors();

        $gallery = $company->fresh()->offer_gallery;

        $this->assertCount(1, $gallery);
        Storage::disk('public')->assertMissing('companies/vecchia.jpg');
        Storage::disk('public')->assertExists($gallery[0]);
    }

    public function test_non_si_cancellano_file_che_non_sono_della_scheda(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('brand/logo.png', 'x');
        $company = $this->company();

        $this->actingAs($this->admin())
            ->put(route('admin.companies.update', $company), $this->payload($company, [
                'remove_gallery' => ['brand/logo.png'],
            ]))
            ->assertSessionHasNoErrors();

        Storage::disk('public')->assertExists('brand/logo.png');
    }

    public function test_la_galleria_tiene_al_massimo_dieci_foto(): void
    {
        Storage::fake('public');
        $company = $this->company([
            'offer_gallery' => array_map(fn ($i) => "companies/$i.jpg", range(1, 10)),
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.companies.update', $company), $this->payload($company, [
                'gallery' => [UploadedFile::fake()->image('undicesima.jpg')],
            ]))
            ->assertSessionHasErrors('gallery');

        $this->assertCount(10, $company->fresh()->offer_gallery);
    }

    public function test_il_sito_segnaposto_del_vecchio_database_non_blocca_il_salvataggio(): void
    {
        $company = $this->company(['website' => '-']);

        $this->actingAs($this->admin())
            ->put(route('admin.companies.update', $company), $this->payload($company, ['website' => '-']))
            ->assertSessionHasNoErrors();

        $this->assertNull($company->fresh()->website);
    }

    public function test_una_nuova_azienda_nasce_con_il_suo_accesso(): void
    {
        $plan = $this->plan('Biglietto', null);

        $this->actingAs($this->admin())
            ->post(route('admin.companies.store'), [
                'name' => 'Grano Salis',
                'login_email' => 'grano@example.test',
                'password' => 'NuovaPassword2026!',
                'password_confirmation' => 'NuovaPassword2026!',
                'plan_id' => $plan->id,
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $company = Company::where('name', 'Grano Salis')->firstOrFail();

        $this->assertSame('grano@example.test', $company->user->email);
        $this->assertSame('vendor', $company->user->user_type);
        $this->assertNotNull($company->user->email_verified_at);
        $this->assertSame('grano-salis', $company->slug);
        $this->assertNull($company->activeSubscription()->ends_at);
    }

    public function test_l_elenco_cerca_anche_per_email(): void
    {
        $this->company(['name' => 'Grano Salis', 'email' => 'granosalis@example.test']);
        $this->company(['name' => 'Calabria Sapori', 'email' => 'calabria@example.test']);

        $this->actingAs($this->admin())
            ->get(route('admin.companies.index', ['cerca' => 'granosalis']))
            ->assertOk()
            ->assertSee('Grano Salis')
            ->assertDontSee('Calabria Sapori');
    }
}
