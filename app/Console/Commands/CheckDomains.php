<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Domain;
use App\Support\Domains\DomainConnectionChecker;
use Illuminate\Console\Command;

/**
 * Il giro delle verifiche dei domini.
 *
 * Un cliente cambia il DNS quando vuole, e il certificato arriva qualche
 * minuto dopo: senza un giro periodico lo stato in amministrazione
 * resterebbe fermo al giorno in cui qualcuno ha premuto "Verifica ora".
 */
class CheckDomains extends Command
{
    protected $signature = 'domains:check';

    protected $description = 'Verifica DNS e certificato dei domini collegati alla piattaforma';

    public function handle(DomainConnectionChecker $checker): int
    {
        $checked = 0;
        $connected = 0;

        $targets = [
            [Domain::query()->where('is_active', true), 'domain'],
            [Company::query()->whereNotNull('custom_domain')->where('custom_domain', '!=', ''), 'custom_domain'],
        ];

        foreach ($targets as [$query, $column]) {
            foreach ($query->lazyById(200) as $model) {
                $connected += $checker->refresh($model, $model->$column, register: false)->connected() ? 1 : 0;
                $checked++;
            }
        }

        $this->info("Domini verificati: $checked. Collegati: $connected.");

        return self::SUCCESS;
    }
}
