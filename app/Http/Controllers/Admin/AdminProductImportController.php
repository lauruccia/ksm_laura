<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\ProductCsv\WooCommerceProductImporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Importa i prodotti di un'azienda da un CSV in formato WooCommerce, dall'amministrazione (05/11/2026).
 *
 * Stessa procedura dell'area azienda (vedi VendorProductImportController): l'admin
 * sceglie prima l'azienda di destinazione, poi carica il file, guarda l'anteprima
 * e conferma. Il file puo' venire da kmoney o da WooCommerce.
 */
class AdminProductImportController extends Controller
{
    public function create(Request $request): View
    {
        $company = $request->integer('azienda') ? Company::find($request->integer('azienda')) : null;
        $term = trim($request->string('cerca')->toString());

        return view('admin.products.import', [
            'plan' => null,
            'company' => $company,
            'term' => $term,
            'companies' => $company || $term === '' ? collect() : Company::query()
                ->where('name', 'like', '%'.addcslashes($term, '\\%_').'%')
                ->orderBy('name')
                ->take(20)
                ->get(['id', 'name', 'city', 'is_active']),
        ]);
    }

    public function analyze(Request $request, WooCommerceProductImporter $importer): View|RedirectResponse
    {
        $request->validate([
            'azienda' => ['required', 'integer', 'exists:companies,id'],
            'csv' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
            'images' => ['nullable', 'boolean'],
        ]);

        $company = Company::findOrFail($request->integer('azienda'));
        $dir = 'product-csv/admin-'.$request->user()->id;
        $this->purgeOldFiles($dir);

        $token = (string) Str::uuid();
        $request->file('csv')->storeAs($dir, $token.'.csv', 'local');

        $opts = ['images' => $request->boolean('images')];
        $plan = $importer->plan(Storage::disk('local')->path("$dir/$token.csv"), $company, $opts);

        if ($plan['fatal'] !== null) {
            Storage::disk('local')->delete("$dir/$token.csv");

            return back()->withInput()->withErrors(['csv' => $plan['fatal']]);
        }

        return view('admin.products.import', [
            'plan' => $plan, 'token' => $token, 'opts' => $opts,
            'company' => $company, 'term' => '', 'companies' => collect(),
        ]);
    }

    public function apply(Request $request, WooCommerceProductImporter $importer): RedirectResponse
    {
        $request->validate([
            'azienda' => ['required', 'integer', 'exists:companies,id'],
            'token' => ['required', 'uuid'],
            'images' => ['nullable', 'boolean'],
        ]);

        $company = Company::findOrFail($request->integer('azienda'));
        $rel = 'product-csv/admin-'.$request->user()->id.'/'.$request->input('token').'.csv';

        if (! Storage::disk('local')->exists($rel)) {
            return redirect()->route('admin.products.import', ['azienda' => $company->id])
                ->withErrors(['csv' => 'Il file non c\'e\' piu\': ricaricalo.']);
        }

        $opts = ['images' => $request->boolean('images')];
        $plan = $importer->plan(Storage::disk('local')->path($rel), $company, $opts);

        if ($plan['fatal'] !== null) {
            return back()->withErrors(['csv' => $plan['fatal']]);
        }

        $result = $importer->apply($plan, $company, $opts);
        Storage::disk('local')->delete($rel);

        $message = "Import finito per {$company->name}: {$result['created']} nuovi, {$result['updated']} aggiornati, {$result['skipped']} già uguali.";

        $redirect = redirect()->route('admin.products.index', ['azienda' => $company->id])->with('success', $message);

        return $result['errors'] === []
            ? $redirect
            : $redirect->withErrors(['import' => 'Non importati: '.implode(' ', $result['errors'])]);
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
