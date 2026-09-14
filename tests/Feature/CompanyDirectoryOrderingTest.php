<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Support\CompanyDirectory;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Ordine della directory: le fasce sono fisse, dentro si mescola.
 */
class CompanyDirectoryOrderingTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $name, float $price, int $priority, array $capabilities): Plan
    {
        return Plan::create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'price' => $price,
            'priority' => $priority,
            'duration_days' => 365,
            'is_active' => true,
            'capabilities' => $capabilities,
        ]);
    }

    private function company(string $name, ?Plan $plan): Company
    {
        $user = User::create([
            'name' => $name,
            'email' => str($name)->slug()->toString().'@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        return Company::create([
            'user_id' => $user->id,
            'plan_id' => $plan?->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'is_active' => true,
        ]);
    }

    /** @return array{0: Plan, 1: Plan} ecommerce e anagrafica */
    private function twoTiers(): array
    {
        $base = [PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD];

        return [
            $this->plan('Ecommerce', 349, 40, array_merge($base, [PlanCapabilities::SHOP])),
            $this->plan('Anagrafica', 19, 10, $base),
        ];
    }

    /** Le query eseguite, senza virgolette: SQLite usa ", MySQL il backtick. */
    private function queryLog()
    {
        return collect(DB::getQueryLog())->map(fn ($entry) => str_replace(['"', '`'], '', $entry['query']));
    }

    public function test_le_fasce_restano_in_ordine_di_piano(): void
    {
        [$alto, $basso] = $this->twoTiers();

        foreach (range(1, 4) as $i) {
            $this->company("Economica $i", $basso);
            $this->company("Costosa $i", $alto);
        }

        $names = (new CompanyDirectory())
            ->take(Company::query()->active(), 7, 8)
            ->pluck('name');

        $this->assertCount(4, $names->take(4)->filter(fn ($n) => str_starts_with($n, 'Costosa')));
        $this->assertCount(4, $names->skip(4)->filter(fn ($n) => str_starts_with($n, 'Economica')));
    }

    public function test_dentro_la_fascia_l_ordine_dipende_dal_seme(): void
    {
        [$alto] = $this->twoTiers();

        foreach (range(1, 8) as $i) {
            $this->company("Azienda $i", $alto);
        }

        $directory = new CompanyDirectory();

        $primo = $directory->take(Company::query()->active(), 1, 8)->pluck('id')->all();
        $stesso = $directory->take(Company::query()->active(), 1, 8)->pluck('id')->all();
        $altro = $directory->take(Company::query()->active(), 2, 8)->pluck('id')->all();

        // Stesso seme, stesso ordine: la pagina due resta coerente con la uno.
        $this->assertSame($primo, $stesso);
        $this->assertNotSame($primo, $altro);
        $this->assertEqualsCanonicalizing($primo, $altro);
    }

    public function test_le_aziende_senza_piano_finiscono_in_fondo(): void
    {
        [, $basso] = $this->twoTiers();

        $senzaPiano = $this->company('Senza piano', null);
        $this->company('Con piano', $basso);

        $ids = (new CompanyDirectory())
            ->take(Company::query()->active(), 5, 8)
            ->pluck('id')
            ->all();

        $this->assertSame($senzaPiano->id, end($ids));
    }

    public function test_la_paginazione_non_ripete_e_non_salta_aziende(): void
    {
        $base = [PlanCapabilities::DIRECTORY];
        $fasce = [
            'Ecommerce' => [$this->plan('Ecommerce', 349, 40, $base), 7],
            // Stessa priorita' di Ecommerce ma prezzo piu' basso: viene dopo.
            'Vetrina' => [$this->plan('Vetrina', 99, 40, $base), 6],
            'Anagrafica' => [$this->plan('Anagrafica', 19, 10, $base), 9],
            'Senza piano' => [null, 3],
        ];

        $fasciaDi = [];
        foreach (array_keys($fasce) as $posizione => $nome) {
            [$plan, $quante] = $fasce[$nome];
            foreach (range(1, $quante) as $i) {
                $fasciaDi[$this->company("$nome $i", $plan)->id] = $posizione;
            }
        }

        $directory = new CompanyDirectory();
        $visti = [];

        foreach (range(1, 7) as $pagina) {
            Paginator::currentPageResolver(fn () => $pagina);
            $risultato = $directory->paginate(Company::query()->active(), 42, 4);

            $this->assertSame(25, $risultato->total());
            array_push($visti, ...$risultato->pluck('id')->all());
        }

        $this->assertSame(array_values(array_unique($visti)), $visti, 'nessuna azienda ripetuta');
        $this->assertEqualsCanonicalizing(array_keys($fasciaDi), $visti, 'nessuna azienda saltata');

        $fasceViste = array_map(fn ($id) => $fasciaDi[$id], $visti);
        $inOrdine = $fasceViste;
        sort($inOrdine);
        $this->assertSame($inOrdine, $fasceViste, 'fasce per priorita\', poi prezzo, poi senza piano');

        // Le pagine una dopo l'altra danno lo stesso mazzo di un'unica lettura.
        $this->assertSame($visti, $directory->take(Company::query()->active(), 42, 25)->pluck('id')->all());
    }

    public function test_a_php_arrivano_solo_le_aziende_della_pagina(): void
    {
        [$alto, $basso] = $this->twoTiers();

        foreach (range(1, 10) as $i) {
            $this->company("Azienda $i", $i % 2 ? $alto : $basso);
        }

        DB::enableQueryLog();
        Paginator::currentPageResolver(fn () => 2);

        $pagina = (new CompanyDirectory())->paginate(Company::query()->active(), 11, 4);

        $query = $this->queryLog()
            ->filter(fn ($sql) => str_contains($sql, 'from companies'))
            ->values();

        $this->assertCount(4, $pagina);
        $this->assertCount(3, $query, 'conteggio, id della pagina, aziende della pagina');
        $this->assertStringContainsString('count(*)', $query[0]);
        $this->assertStringContainsString('limit 4 offset 4', $query[1]);
        $this->assertStringContainsString('companies.id in (?, ?, ?, ?)', $query[2]);

        // Nell'ordine solo aritmetica intera: nessuna funzione che esiste su un database solo.
        $ordine = Str::between($query[1], ' order by ', ' limit ');
        $this->assertDoesNotMatchRegularExpression('/[a-z_]\s*\(/i', $ordine);
    }

    public function test_la_home_chiede_al_database_solo_le_aziende_in_vetrina(): void
    {
        [$alto] = $this->twoTiers();

        foreach (range(1, 10) as $i) {
            $this->company("Azienda $i", $alto);
        }

        DB::enableQueryLog();

        $this->get(route('home'))->assertOk();

        $ordine = $this->queryLog()->first(fn ($sql) => str_contains($sql, 'left join plans'));

        $this->assertStringContainsString('limit 8', $ordine);
    }

    public function test_la_pagina_pubblica_mostra_solo_chi_ha_la_directory_nel_piano(): void
    {
        $senzaDirectory = $this->plan('Riservato', 9, 5, [PlanCapabilities::CONTACT_CARD]);
        [, $conDirectory] = $this->twoTiers();

        $this->company('Visibile', $conDirectory);
        $this->company('Nascosta', $senzaDirectory);

        $this->get(route('companies.index'))
            ->assertOk()
            ->assertSee('Visibile')
            ->assertDontSee('Nascosta');
    }
}
