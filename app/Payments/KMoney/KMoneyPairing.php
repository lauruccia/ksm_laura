<?php

namespace App\Payments\KMoney;

use App\Models\Company;
use App\Models\CompanyPaymentSetting;
use App\Payments\PaymentException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Collegamento a KMoney con il solo numero di conto.
 *
 * 1. il venditore scrive il numero, KSM chiede il collegamento con un
 *    segreto di ritiro che conosce solo lui;
 * 2. l'amministrazione KMoney approva o rifiuta;
 * 3. KSM ritira token e segreto del webhook, che KMoney consegna in
 *    chiaro una volta sola, e li salva cifrati.
 *
 * Nessuna credenziale passa dalle mani del venditore.
 */
class KMoneyPairing
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /** Approvato, ma le credenziali le ha ritirate qualcun altro: va richiesto di nuovo. */
    public const LOST = 'lost';

    public function __construct(private readonly KMoneyTradingSync $sync)
    {
    }

    /** Cosa dire a chi guarda lo stato del collegamento. */
    public static function describe(?string $status): string
    {
        return match ($status) {
            self::PENDING => __('Richiesta inviata: in attesa che KMoney la approvi.'),
            self::APPROVED => __('Conto KMoney collegato.'),
            self::REJECTED => __('KMoney ha rifiutato il collegamento.'),
            self::LOST => __('Collegamento non più valido: richiedilo di nuovo.'),
            default => __('Nessun collegamento KMoney.'),
        };
    }

    /** "kyb 0000 0109z7xbh" diventa "KYB00000109Z7XBH". */
    public static function normalize(?string $accountNumber): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', (string) $accountNumber));
    }

    /** KYB (aziende) o KYP (privati) piu' 13 caratteri. */
    public static function isValidAccount(string $accountNumber): bool
    {
        return (bool) preg_match('/^KY[BP][A-Z0-9]{13}$/', $accountNumber);
    }

    public function request(Company $company, string $accountNumber): CompanyPaymentSetting
    {
        $account = self::normalize($accountNumber);

        if (! self::isValidAccount($account)) {
            throw new PaymentException('Numero di conto KMoney non valido.');
        }

        $secret = Str::random(48);

        $data = KMoneyClient::anonymous()->requestPairing(
            $account,
            // L'indirizzo da cui si sta chiedendo, non APP_URL: e' quello che KMoney deve poter chiamare.
            url('/'),
            route('webhooks.kmoney', $company),
            $secret,
        );

        if (blank($data['uuid'] ?? null)) {
            throw new PaymentException('KMoney non ha restituito la richiesta di collegamento.');
        }

        $settings = CompanyPaymentSetting::firstOrCreate(['company_id' => $company->id]);

        // Una nuova richiesta per lo stesso sito e conto sostituisce quella in attesa.
        $settings->forceFill([
            'kmoney_account_number' => $account,
            'kmoney_pairing_uuid' => $data['uuid'],
            'kmoney_pairing_secret' => $secret,
            'kmoney_pairing_status' => self::PENDING,
            'kmoney_pairing_requested_at' => now(),
            'kmoney_pairing_checked_at' => null,
        ])->save();

        return $settings;
    }

    /**
     * Chiede a KMoney com'e' andata e, se approvato, ritira le credenziali.
     *
     * Il lucchetto evita che il pulsante e il giro periodico ritirino
     * insieme: il secondo troverebbe le credenziali gia' consegnate.
     */
    public function check(Company $company): ?string
    {
        return Cache::lock('kmoney-pairing-'.$company->id, 30)->block(10, function () use ($company) {
            $settings = $company->paymentSettings()->first();

            if ($settings?->kmoney_pairing_status !== self::PENDING
                || blank($settings->kmoney_pairing_uuid)
                || blank($settings->kmoney_pairing_secret)) {
                return $settings?->kmoney_pairing_status;
            }

            try {
                $data = KMoneyClient::anonymous()->pairing($settings->kmoney_pairing_uuid, $settings->kmoney_pairing_secret);
            } catch (PaymentException $e) {
                // 404: richiesta sostituita da una nuova o sconosciuta a KMoney.
                if ($e->getCode() !== 404) {
                    throw $e;
                }

                return $this->close($settings, self::LOST);
            }

            $status = $data['status'] ?? null;

            if ($status === self::APPROVED && filled($data['api_token'] ?? null) && filled($data['webhook_secret'] ?? null)) {
                $settings->forceFill([
                    'kmoney_api_token' => $data['api_token'],
                    'kmoney_webhook_secret' => $data['webhook_secret'],
                    'enable_kmoney' => true,
                ]);
                $this->close($settings, self::APPROVED);
                $this->syncQuietly($company);

                return self::APPROVED;
            }

            if ($status === self::APPROVED) {
                return $this->close($settings, self::LOST);
            }

            if ($status === self::REJECTED) {
                return $this->close($settings, self::REJECTED);
            }

            $settings->forceFill(['kmoney_pairing_checked_at' => now()])->save();

            return self::PENDING;
        });
    }

    /** Il segreto di ritiro non serve piu': non resta nel database. */
    private function close(CompanyPaymentSetting $settings, string $status): string
    {
        $settings->forceFill([
            'kmoney_pairing_status' => $status,
            'kmoney_pairing_secret' => null,
            'kmoney_pairing_checked_at' => now(),
        ])->save();

        return $status;
    }

    /** Il collegamento e' riuscito anche se la prima lettura dello stato no: ci riprova il giro orario. */
    private function syncQuietly(Company $company): void
    {
        try {
            $this->sync->sync($company);
        } catch (PaymentException $e) {
            Log::warning('Stato KMoney non letto dopo il collegamento', ['company' => $company->id, 'error' => $e->getMessage()]);
        }
    }
}
