<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Plan;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Modello unico delle schede per i motori di ricerca: titolo, tipo schema.org,
 * breadcrumb e link alle pagine categoria e alle aziende simili.
 */
class CompanySeoTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    private CompanyCategory $dentisti;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->plan = Plan::create([
            'name' => 'Vetrina', 'slug' => 'vetrina', 'price' => 120, 'priority' => 30, 'duration_days' => 365,
            'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD, PlanCapabilities::SHOWCASE],
        ]);

        $this->dentisti = CompanyCategory::create(['name' => 'Dentisti', 'slug' => 'dentisti']);
    }

    private function company(string $name, ?string $city = 'Roma'): Company
    {
        $user = User::create([
            'name' => $name, 'email' => Str::slug($name).'@example.test', 'password' => 'password', 'user_type' => 'vendor',
        ]);

        return Company::create([
            'user_id' => $user->id, 'plan_id' => $this->plan->id, 'category_id' => $this->dentisti->id,
            'name' => $name, 'slug' => Str::slug($name), 'city' => $city, 'is_active' => true,
        ]);
    }

    public function test_titolo_tipo_e_breadcrumb_della_scheda(): void
    {
        $company = $this->company('Studio Rossi');
        $this->company('Studio Bianchi');
        $this->company('Studio Verdi');

        $page = $this->get(route('companies.show', $company->slug))->assertOk();

        $page->assertSee('<title>Studio Rossi · Dentisti a Roma | ', false);
        $this->assertStringContainsString('"@type":"Dentist"', $page->getContent());
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $page->getContent());
        $page->assertSee(route('categories.show', ['category' => 'dentisti', 'place' => 'roma']), false);
    }

    public function test_la_scheda_linka_le_aziende_simili_a_catena(): void
    {
        $uno = $this->company('Studio Rossi');
        $due = $this->company('Studio Bianchi');
        $tre = $this->company('Studio Verdi');
        $altraCitta = $this->company('Studio Neri', 'Milano');

        $this->get(route('companies.show', $tre->slug))
            ->assertOk()
            ->assertSee(route('companies.show', $uno->slug), false)
            ->assertSee(route('companies.show', $due->slug), false)
            ->assertDontSee(route('companies.show', $altraCitta->slug), false);
    }
}
