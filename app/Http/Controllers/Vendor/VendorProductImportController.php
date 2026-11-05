<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Support\ProductCsv\WooCommerceProductImporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Importa i prodotti dell'azienda da un CSV in formato WooCommerce (05/11/2026).
 *
 * Il file puo' venire da kmoney (con la quota KMoney di ogni prodotto) o da
 * WooCommerce. Due tempi: si carica il file e si vede cosa cambierebbe, poi si
 * conferma. Vedi WooCommerceProductImporter.
 */
class VendorProductImportController extends Controller
{
    public function create(): View
    {
        return view('vendor.products.import', ['plan' => null]);
    }

    public function analyze(Request $request, WooCommerceProductImporter $importer): View|RedirectResponse
    {
        $request->validate([
            'csv' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
            'images' => ['nullable', 'boolean'],
        ]);

        $company = $request->user()->company;
        $dir = 'product-csv/'.$request->user()->id;
        $this->purgeOldFiles($dir);

        $token = (string) Str::uuid();
        $request->file('csv')->storeAs($dir, $token.'.csv', 'local');

        $opts = ['images' => $request->boolean('images')];
        $plan = $importer->plan(Storage::disk('local')->path("$dir/$token.csv"), $company, $opts);

        if ($plan['fatal'] !== null) {
            Storage::disk('local')->delete("$dir/$token.csv");

            return back()->withInput()->withErrors(['csv' => $plan['fatal']]);
        }

        return view('vendor.products.import', ['plan' => $plan, 'token' => $token, 'opts' => $opts]);
    }

    public function apply(Request $request, WooCommerceProductImporter $importer): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'uuid'],
            'images' => ['nullable', 'boolean'],
        ]);

        $company = $request->user()->company;
        $rel = 'product-csv/'.$request->user()->id.'/'.$request->input('token').'.csv';

        if (! Storage::disk('local')->exists($rel)) {
            return redirect()->route('vendor.products.import')->withErrors(['csv' => 'Il file non c\'e\' piu\': ricaricalo.']);
        }

        $opts = ['images' => $request->boolean('images')];
        $plan = $importer->plan(Storage::disk('local')->path($rel), $company, $opts);

        if ($plan['fatal'] !== null) {
            return back()->withErrors(['csv' => $plan['fatal']]);
        }

        $result = $importer->apply($plan, $company, $opts);
        Storage::disk('local')->delete($rel);

        $message = "Import finito: {$result['created']} nuovi, {$result['updated']} aggiornati, {$result['skipped']} già uguali.";

        $redirect = redirect()->route('vendor.products.index');

        return $result['errors'] === []
            ? $redirect->with('success', $message)
            : $redirect->with('success', $message)->withErrors(['import' => 'Non importati: '.implode(' ', $result['errors'])]);
    }

    /** I file in attesa di conferma servono pochi minuti: via quelli di piu' di un giorno. */
    private function purgeOldFiles(string $dir): void
    {
        $disk = Storage::disk('local');

        foreach ($disk->files($dir) as $file) {
            if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }
}
