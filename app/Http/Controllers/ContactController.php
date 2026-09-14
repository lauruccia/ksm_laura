<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Models\AdminSetting;
use App\Models\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('pages.contact', ['settings' => AdminSetting::current()]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $settings = AdminSetting::current();

        if ($settings->website_email) {
            Mail::to($settings->website_email)->send(new ContactMessage($data));
        }

        return back()->with('success', __('Messaggio inviato.'));
    }

    public function sendToCompany(Request $request, Company $company): RedirectResponse
    {
        $data = $this->validated($request);

        if ($company->email) {
            Mail::to($company->email)->send(new ContactMessage($data, $company->name));
        }

        return back()->with('success', __('Messaggio inviato all azienda.'));
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
