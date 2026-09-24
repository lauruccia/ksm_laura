<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Payments\KMoney\KMoneyPairing;
use App\Payments\PaymentException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Il giro dei collegamenti KMoney in attesa di approvazione.
 *
 * L'amministrazione KMoney approva quando vuole: senza questo giro il
 * venditore dovrebbe tornare a premere "Controlla ora".
 */
class CheckKMoneyPairings extends Command
{
    protected $signature = 'kmoney:pairings';

    protected $description = 'Ritira token e segreto dei collegamenti KMoney approvati';

    public function handle(KMoneyPairing $pairing): int
    {
        $counts = [];

        $companies = Company::query()->whereHas(
            'paymentSettings',
            fn ($q) => $q->where('kmoney_pairing_status', KMoneyPairing::PENDING)
        );

        foreach ($companies->lazyById(100) as $company) {
            try {
                $status = $pairing->check($company) ?? 'nessuno';
            } catch (PaymentException $e) {
                $status = 'errore';
                Log::warning('Collegamento KMoney non verificato', ['company' => $company->id, 'error' => $e->getMessage()]);
            }

            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        $this->info('Collegamenti verificati: '.(collect($counts)->map(fn ($n, $s) => "$s $n")->implode(', ') ?: 'nessuno').'.');

        return self::SUCCESS;
    }
}
