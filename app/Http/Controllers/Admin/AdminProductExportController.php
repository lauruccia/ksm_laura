<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Support\ProductCsv\WooCommerceProductExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Esporta i prodotti di un'azienda in CSV nel formato di WooCommerce (20/11/2026).
 *
 * Il file si importa in ksm, in kmoney e in WooCommerce. Serve l'azienda: gli SKU
 * sono unici solo dentro l'azienda e l'import lavora su un'azienda alla volta.
 * Rispetta i filtri dell'elenco prodotti (cerca, stato).
 */
class AdminProductExportController extends Controller
{
    public function export(Request $request, WooCommerceProductExporter $exporter): StreamedResponse
    {
        $request->validate(['azienda' => ['required', 'integer', 'exists:companies,id']]);

        $company = Company::findOrFail($request->integer('azienda'));

        $query = Product::query()
            ->where('company_id', $company->id)
            ->when($request->string('cerca')->toString(), fn ($q, $t) => $q->where('name', 'like', '%'.addcslashes($t, '\\%_').'%'))
            ->when(in_array($request->input('stato'), ['active', 'inactive'], true), fn ($q) => $q->where('status', $request->input('stato')));

        $name = 'prodotti-'.Str::slug($company->name).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($exporter, $query) {
            $out = fopen('php://output', 'w');
            $exporter->write($out, $query);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
