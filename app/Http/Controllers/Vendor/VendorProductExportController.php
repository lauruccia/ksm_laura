<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Support\ProductCsv\WooCommerceProductExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Esporta i prodotti dell'azienda in CSV nel formato di WooCommerce (20/11/2026).
 * Vedi WooCommerceProductExporter. Rispetta i filtri dell'elenco (cerca, stato).
 */
class VendorProductExportController extends Controller
{
    public function export(Request $request, WooCommerceProductExporter $exporter): StreamedResponse
    {
        $company = $request->user()->company;

        $query = $company->products()->getQuery()
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
