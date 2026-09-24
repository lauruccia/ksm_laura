<?php

namespace App\Jobs;

use App\Models\Company;
use App\Payments\KMoney\KMoneyTradingSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Rilegge lo stato del conto KMoney dopo la notifica
 * company.trading_status_changed, gia' verificata.
 *
 * Il contenuto della notifica non si usa: fa fede GET /balance.
 */
class SyncKMoneyTradingStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public function __construct(public readonly int $companyId)
    {
    }

    /** Attese fra un tentativo e l'altro, in secondi. */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(KMoneyTradingSync $sync): void
    {
        $company = Company::find($this->companyId);

        if ($company) {
            $sync->sync($company);
        }
    }
}
