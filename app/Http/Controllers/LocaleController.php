<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function __invoke(string $locale): RedirectResponse
    {
        if (array_key_exists($locale, config('ksm.locales'))) {
            session(['locale' => $locale]);
        }

        return back();
    }
}
