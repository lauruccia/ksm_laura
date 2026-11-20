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
 * Il file si importa in ksm, in kmoney e in WooCommerce. Esporta i prodotti dell'elenco
 * con gli stessi filtri (nome, azienda, stato). Se l'elenco non e' ristretto a una sola
 * azienda aggiunge la colonna "Azienda": l'import lavora su un'azienda alla volta, e gli
 * SKU sono unici solo dentro l'azienda.
 */
class AdminProductExportController extends Controller
{
    public function export(Request $request, WooCommerceProductExporter $exporter): StreamedResponse
    {
        $request->validate(['azienda' => ['nullable', 'integer', 'exists:companies,id']]);

        $company = $request->integer('azienda') ? Company::find($request->integer('azienda')) : null;
        $query = app(AdminProductController::class)->filters(Product::query(), $request);

        $name = 'prodotti-'.Str::slug($company?->name ?? 'tutte-le-aziende').'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($exporter, $query, $company) {
            $out = fopen('php://output', 'w');
            $exporter->write($out, $query, withCompany: $company === null);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
