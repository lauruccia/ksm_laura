<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Models\AdminSetting;
use App\Models\Company;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function show(TenantContext $tenant): View
    {
        $settings = AdminSetting::current();
        $domain = $tenant->domain();

        // Su un dominio della rete i recapiti sono i suoi, non quelli di KSM.
        return view('pages.contact', ['contacts' => $domain ? [
            'address' => collect([$domain->address, $domain->city])->filter()->implode(', '),
            'phone' => $domain->phone,
            'email' => $domain->email,
            'map' => null,
        ] : [
            'address' => $settings->address,
            'phone' => $settings->contact_number,
            'email' => $settings->website_email,
            'map' => $settings->location_map_embed,
        ]]);
    }

    public function send(Request $request, TenantContext $tenant): RedirectResponse
    {
        $data = $this->validated($request);

        // Il messaggio va a chi gestisce il sito: l'email del dominio, se c'e', altrimenti KSM.
        $recipient = $tenant->domain()?->email ?: AdminSetting::current()->website_email;

        if ($recipient && ! $this->deliver($recipient, new ContactMessage($data))) {
            return $this->failed();
        }

        return back()->with('success', __('Messaggio inviato.'));
    }

    public function sendToCompany(Request $request, Company $company): RedirectResponse
    {
        // Il modulo sta sulla pagina dell'azienda: senza pagina non c'e'.
        abort_unless($company->hasPage(), 404);

        $data = $this->validated($request);

        if ($company->email && ! $this->deliver($company->email, new ContactMessage($data, $company->name))) {
            return $this->failed();
        }

        return back()->with('success', __('Messaggio inviato all azienda.'));
    }

    /**
     * Spedisce subito, senza coda: chi scrive deve sapere se e' partito.
     * Se il server di posta non risponde si scrive nel log e si avvisa,
     * invece di una pagina di errore che fa perdere il testo scritto.
     */
    private function deliver(string $recipient, ContactMessage $message): bool
    {
        try {
            Mail::to($recipient)->send($message);

            return true;
        } catch (\Throwable $e) {
            Log::error('Messaggio del modulo contatti non spedito', ['to' => $recipient, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function failed(): RedirectResponse
    {
        return back()->withInput()->withErrors([
            'message' => __('Non siamo riusciti a spedire il messaggio. Riprova tra qualche minuto.'),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);
    }
}
