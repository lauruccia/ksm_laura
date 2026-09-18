<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Support\Maps\Geocoder;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Mappa OpenStreetMap: coordinate trovate dall'indirizzo, segnaposto a mano, mappa pubblica.
 *
 * Nominatim e' finto: si prova quando lo chiamiamo e cosa ne facciamo.
 */
class CompanyMapTest extends TestCase
{
    use RefreshDatabase;

    private const NOMINATIM = 'nominatim.openstreetmap.org/*';

    private function fakeNominatim(array $results = [['lat' => '41.6380000', 'lon' => '12.4870000']], int $status = 200): void
    {
        Http::fake([self::NOMINATIM => Http::response($results, $status)]);
    }

    private function plan(array $capabilities): Plan
    {
        return Plan::create([
            'name' => 'Piano '.uniqid(), 'slug' => 'piano-'.uniqid(), 'price' => 240, 'priority' => 10,
            'duration_days' => null, 'is_active' => true, 'capabilities' => $capabilities,
        ]);
    }

    private function company(array $attributes = []): Company
    {
        $owner = User::create([
            'name' => 'Titolare', 'email' => 'titolare'.uniqid().'@example.test',
            'password' => 'password', 'user_type' => 'vendor',
        ]);

        return Company::create($attributes + [
            'user_id' => $owner->id,
            'plan_id' => $this->plan([PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD, PlanCapabilities::SHOWCASE, PlanCapabilities::SHOP])->id,
            'name' => 'Decina Bus',
            'slug' => 'decina-bus-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => Role::SUPER_ADMIN],
            ['name' => 'Super amministratore', 'is_system' => true]
        );

        return User::create([
            'name' => 'Amministratore', 'email' => 'admin'.uniqid().'@example.test',
            'password' => 'password', 'user_type' => 'admin', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function adminUpdate(Company $company, array $fields): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.companies.update', $company), $fields + [
                'name' => $company->name,
                'login_email' => $company->user->email,
                'plan_id' => $company->plan_id,
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_il_geocoder_si_presenta_e_cerca_in_italia(): void
    {
        $this->fakeNominatim();

        $point = app(Geocoder::class)->locate('Via Armando Veneziani 7, Pomezia');

        $this->assertSame([41.638, 12.487], $point);
        Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', config('ksm.maps.contact'))
            && $request['countrycodes'] === 'it'
            && $request['q'] === 'Via Armando Veneziani 7, Pomezia');
    }

    public function test_salvando_un_indirizzo_nuovo_si_trovano_le_coordinate(): void
    {
        $this->fakeNominatim();
        $company = $this->company();

        $this->adminUpdate($company, ['company_location' => 'Via Armando Veneziani 7, Pomezia']);

        $company->refresh();
        $this->assertEquals(41.638, (float) $company->latitude);
        $this->assertEquals(12.487, (float) $company->longitude);
    }

    public function test_il_segnaposto_spostato_a_mano_vince_e_non_si_cerca_niente(): void
    {
        Http::fake();
        $company = $this->company(['company_location' => 'Pomezia', 'latitude' => 41.6, 'longitude' => 12.5]);

        $this->adminUpdate($company, [
            'company_location' => 'Via Armando Veneziani 7, Pomezia',
            'latitude' => '41.6391234',
            'longitude' => '12.4881234',
        ]);

        Http::assertNothingSent();
        $this->assertEquals(41.6391234, (float) $company->fresh()->latitude);
    }

    public function test_se_l_indirizzo_non_cambia_non_si_cerca_di_nuovo(): void
    {
        Http::fake();
        $company = $this->company(['company_location' => 'Pomezia', 'latitude' => 41.6, 'longitude' => 12.5]);

        $this->adminUpdate($company, ['company_location' => 'Pomezia', 'latitude' => '41.6', 'longitude' => '12.5']);

        Http::assertNothingSent();
        $this->assertEquals(41.6, (float) $company->fresh()->latitude);
    }

    public function test_se_nominatim_non_risponde_si_salva_lo_stesso_senza_mappa(): void
    {
        $this->fakeNominatim([], 503);
        $company = $this->company();

        $this->adminUpdate($company, ['company_location' => 'Via che non esiste 99']);

        $this->assertNull($company->fresh()->latitude);
        $this->assertSame('Via che non esiste 99', $company->fresh()->company_location);
    }

    public function test_anche_l_azienda_dal_suo_profilo_trova_la_posizione(): void
    {
        $this->fakeNominatim();
        $company = $this->company();

        $this->actingAs($company->user)
            ->put(route('vendor.profile.update'), [
                'name' => $company->name,
                'address' => 'Via Armando Veneziani 7',
                'city' => 'Pomezia',
            ])
            ->assertSessionHasNoErrors();

        Http::assertSent(fn ($request) => $request['q'] === 'Via Armando Veneziani 7, Pomezia');
        $this->assertEquals(41.638, (float) $company->fresh()->latitude);
    }

    public function test_la_scheda_pubblica_mostra_la_mappa_con_le_coordinate(): void
    {
        $company = $this->company(['latitude' => 41.638, 'longitude' => 12.487]);

        $this->get(route('companies.show', $company->slug))
            ->assertOk()
            ->assertSee('data-company-map', false)
            ->assertSee('data-lat="41.638"', false)
            ->assertSee('vendor/leaflet/leaflet.js', false)
            ->assertSee('Indicazioni stradali');
    }

    public function test_senza_coordinate_la_mappa_non_c_e(): void
    {
        $company = $this->company();

        $this->get(route('companies.show', $company->slug))
            ->assertOk()
            ->assertDontSee('data-company-map', false)
            ->assertDontSee('leaflet.js', false);
    }
}
