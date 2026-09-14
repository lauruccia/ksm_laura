<?php

namespace App\Console\Commands;

use App\Support\Legacy\LegacyTestData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeLegacyTestData extends Command
{
    protected $signature = 'legacy:purge-test-data';

    protected $description = 'Toglie aziende, prodotti e marche di prova rimasti dal sito originale';

    public function handle(): int
    {
        $removed = DB::transaction(fn () => (new LegacyTestData())->purge());

        $this->info(sprintf(
            'Dati di prova tolti: aziende %d, utenti %d, prodotti %d, marche %d.',
            $removed['companies'],
            $removed['users'],
            $removed['products'],
            $removed['brands'],
        ));

        return self::SUCCESS;
    }
}
