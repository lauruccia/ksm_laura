<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Payments\KMoney\KMoneyTradingSync;
use App\Payments\PaymentException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Il giro dello stato KMoney dei venditori.
 *
 * La notifica di KMoney aggiorna subito, ma puo' perdersi: il giro
 * periodico rimette in pari debito e capacita' di vendita.
 */
class SyncKMoneyStatus extends Command
{
    protected $signature = 'kmoney:sync';

    protected $description = 'Legge da KMoney debito e capacita di vendita dei venditori collegati';

    public function handle(KMoneyTradingSync $sync): int
    {
        $synced = 0;
        $failed = 0;

        $companies = Company::query()->whereHas('paymentSettings', fn ($q) => $q->whereNotNull('kmoney_api_token'));

        foreach ($companies->lazyById(200) as $company) {
            try {
                $synced += $sync->sync($company) ? 1 : 0;
            } catch (PaymentException $e) {
                $failed++;
                Log::warning('Stato KMoney non letto', ['company' => $company->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Venditori aggiornati: $synced. Non riusciti: $failed.");

        return self::SUCCESS;
    }
}
