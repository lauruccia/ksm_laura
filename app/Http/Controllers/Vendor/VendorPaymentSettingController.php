<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\CompanyPaymentSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VendorPaymentSettingController extends Controller
{
    public function edit(Request $request): View
    {
        $company = $request->user()->company;

        return view('vendor.payments', [
            'settings' => $company->paymentSettings ?? new CompanyPaymentSetting(['company_id' => $company->id]),
            // Indirizzi da incollare nei pannelli di Stripe, PayPal e KMoney.
            'webhooks' => [
                'stripe' => route('webhooks.stripe', $company),
                'paypal' => route('webhooks.paypal', $company),
                'kmoney' => route('webhooks.kmoney', $company),
            ],
        ]);
    }

    /**
     * Le chiavi segrete arrivano dal modulo e restano nel database.
     * I campi vuoti non sovrascrivono quelli gia' salvati.
     */
    public function update(Request $request): RedirectResponse
    {
        $company = $request->user()->company;

        $data = $request->validate([
            'mode' => ['required', 'in:test,live'],
            'enable_stripe' => ['boolean'],
            'enable_paypal' => ['boolean'],
            'enable_kmoney' => ['boolean'],
            'stripe_test_public_key' => ['nullable', 'string', 'max:255'],
            'stripe_test_secret_key' => ['nullable', 'string', 'max:255'],
            'stripe_live_public_key' => ['nullable', 'string', 'max:255'],
            'stripe_live_secret_key' => ['nullable', 'string', 'max:255'],
            'paypal_test_client_id' => ['nullable', 'string', 'max:255'],
            'paypal_test_secret' => ['nullable', 'string', 'max:255'],
            'paypal_live_client_id' => ['nullable', 'string', 'max:255'],
            'paypal_live_secret' => ['nullable', 'string', 'max:255'],
            'kmoney_api_token' => ['nullable', 'string', 'max:255'],
            'kmoney_webhook_secret' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
            'paypal_webhook_id' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = CompanyPaymentSetting::firstOrCreate(['company_id' => $company->id]);
        $settings->fill(array_filter($data, fn ($value) => $value !== null && $value !== ''));
        $settings->enable_stripe = $request->boolean('enable_stripe');
        $settings->enable_paypal = $request->boolean('enable_paypal');
        $settings->enable_kmoney = $request->boolean('enable_kmoney');
        $settings->save();

        return back()->with('success', __('Impostazioni di incasso salvate.'));
    }
}
