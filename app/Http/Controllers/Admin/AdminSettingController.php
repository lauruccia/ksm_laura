<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminPaymentSetting;
use App\Models\AdminSetting;
use App\Models\SmtpSetting;
use App\Support\Images\ImageStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'settings' => AdminSetting::current(),
            'payments' => AdminPaymentSetting::firstOrCreate([]),
            'mail' => SmtpSetting::firstOrCreate(['owner_type' => 'admin']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = AdminSetting::current();

        $data = $request->validate([
            'website_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'website_email' => ['nullable', 'email', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:1000'],
            'header_tagline' => ['nullable', 'string', 'max:255'],
            'header_subline' => ['nullable', 'string', 'max:255'],
            'location_map_embed' => ['nullable', 'string', 'max:2000'],
            'social_links' => ['nullable', 'array'],
            'social_links.facebook' => ['nullable', 'url', 'max:255'],
            'social_links.instagram' => ['nullable', 'url', 'max:255'],
            'base_shipping_rate' => ['nullable', 'numeric', 'min:0'],
            'per_kg_rate' => ['nullable', 'numeric', 'min:0'],
            'site_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'favicon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'hero_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'remove_hero_image' => ['boolean'],
        ]);

        $replaced = [];

        foreach (['site_logo' => 'site_logo', 'favicon' => 'favicon', 'hero_image' => 'banner'] as $field => $profile) {
            if ($request->hasFile($field)) {
                $data[$field] = app(ImageStore::class)->store($request->file($field), 'brand', $profile, $field);
                $replaced[] = $settings->$field;
            }
        }

        if (! $request->hasFile('hero_image') && $request->boolean('remove_hero_image')) {
            $data['hero_image'] = null;
            $replaced[] = $settings->hero_image;
        }
        unset($data['remove_hero_image']);

        // Solo le reti con un indirizzo: il piede mostra le icone di quelle.
        $data['social_links'] = array_map(fn ($url) => $url ?: null, $data['social_links'] ?? []);

        $settings->update($data);
        app(ImageStore::class)->delete($replaced);

        return back()->with('success', __('Impostazioni salvate.'));
    }

    /** Le chiavi lasciate vuote non sovrascrivono quelle gia' presenti. */
    public function updatePayments(Request $request): RedirectResponse
    {
        $settings = AdminPaymentSetting::firstOrCreate([]);

        $data = $request->validate([
            'mode' => ['required', 'in:test,live'],
            'stripe_test_public_key' => ['nullable', 'string', 'max:255'],
            'stripe_test_secret_key' => ['nullable', 'string', 'max:255'],
            'stripe_live_public_key' => ['nullable', 'string', 'max:255'],
            'stripe_live_secret_key' => ['nullable', 'string', 'max:255'],
            'paypal_test_client_id' => ['nullable', 'string', 'max:255'],
            'paypal_test_secret' => ['nullable', 'string', 'max:255'],
            'paypal_live_client_id' => ['nullable', 'string', 'max:255'],
            'paypal_live_secret' => ['nullable', 'string', 'max:255'],
            'kmoney_test_account' => ['nullable', 'string', 'max:255'],
            'kmoney_live_account' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
            'paypal_webhook_id' => ['nullable', 'string', 'max:255'],
            'bank_holder' => ['nullable', 'string', 'max:255'],
            'bank_iban' => ['nullable', 'string', 'max:40'],
            'bank_bic' => ['nullable', 'string', 'max:20'],
            'bank_instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $settings->fill(array_filter($data, fn ($v) => $v !== null && $v !== ''));
        // L'interruttore generale si tocca solo se il modulo lo ha mandato.
        if ($request->has('is_active')) {
            $settings->is_active = $request->boolean('is_active');
        }

        $settings->enable_stripe = $request->boolean('enable_stripe');
        $settings->enable_paypal = $request->boolean('enable_paypal');
        $settings->enable_kmoney = $request->boolean('enable_kmoney');
        $settings->enable_bank_transfer = $request->boolean('enable_bank_transfer');
        // Chi non ha un conto KMoney puo' pagare tutto in euro, tranne dai venditori in debito.
        $settings->kmoney_euro_fallback = $request->boolean('kmoney_euro_fallback');
        $settings->save();

        return back()->with('success', __('Impostazioni di pagamento salvate.'));
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $mail = SmtpSetting::firstOrCreate(['owner_type' => 'admin']);

        $data = $request->validate([
            'mail_mailer' => ['required', 'string', 'max:50'],
            // Accese, spediscono tutte le mail del sito: senza server e porta non si parte.
            'mail_host' => ['nullable', 'required_if:is_active,1', 'string', 'max:255'],
            'mail_port' => ['nullable', 'required_if:is_active,1', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_encryption' => ['nullable', 'in:ssl,tls'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'reply_to_address' => ['nullable', 'email', 'max:255'],
            'reply_to_name' => ['nullable', 'string', 'max:255'],
        ]);

        $mail->fill(array_filter($data, fn ($v) => $v !== null && $v !== ''));
        // "Automatica" e' il valore vuoto: va salvato anche lui.
        $mail->mail_encryption = $data['mail_encryption'] ?? null;
        $mail->is_active = $request->boolean('is_active');
        $mail->save();

        return back()->with('success', __('Impostazioni di posta salvate.'));
    }
}
