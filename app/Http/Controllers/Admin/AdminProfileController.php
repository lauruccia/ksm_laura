<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Il proprio profilo, dentro il pannello.
 *
 * Non chiede permessi: e' di chi ha fatto accesso. Qui non si cambia
 * ne' il tipo ne' il ruolo, che restano decisi da chi gestisce gli utenti.
 */
class AdminProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:50'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        unset($data['current_password']);

        $user->update($data);

        return back()->with('success', __('Profilo aggiornato.'));
    }
}
