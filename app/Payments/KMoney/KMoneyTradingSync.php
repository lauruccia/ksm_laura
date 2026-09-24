<?php

namespace App\Payments\KMoney;

use App\Models\Company;

/**
 * Debito e capacita' di vendita del venditore, letti da KMoney.
 *
 * Il conto in debito porta tutti i prodotti al 100% in KMoney: quando
 * lo stato cambia le quote dei prodotti si ricalcolano subito. Senza
 * token del venditore resta quello che ha impostato l'amministrazione.
 */
class KMoneyTradingSync
{
    public function __construct(private readonly KMoneyPercentages $percentages)
    {
    }

    /** False se l'azienda non ha un conto KMoney collegato. Gli errori dell'API passano. */
    public function sync(Company $company): bool
    {
        $settings = $company->paymentSettings()->first();

        if (! $settings || blank($settings->kmoney_api_token) || blank(config('ksm.kmoney.base_url'))) {
            return false;
        }

        $balance = KMoneyClient::forVendor($settings)->balance();

        $inDebt = (bool) ($balance['is_in_debit'] ?? false);
        $allowed = array_values(array_map('intval', (array) ($balance['allowed_ky_percentages'] ?? [])));
        sort($allowed);
        $sharesChanged = $inDebt !== (bool) $settings->kmoney_in_debt
            || $allowed !== (array) $settings->kmoney_allowed_percentages;

        $settings->forceFill([
            'kmoney_in_debt' => $inDebt,
            'kmoney_can_sell' => array_key_exists('can_sell', $balance) ? (bool) $balance['can_sell'] : null,
            'kmoney_allowed_percentages' => $allowed,
            'kmoney_synced_at' => now(),
        ])->save();

        if ($sharesChanged) {
            $this->percentages->refresh($company);
        }

        return true;
    }
}
