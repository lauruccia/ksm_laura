<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Support\BulkSelection;
use App\Support\Orders\OrderEditor;
use App\Support\Orders\OrderStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminOrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.orders.index', [
            'orders' => $this->filters(Order::with(['company', 'user', 'domain']), $request)
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'statuses' => Order::STATUSES,
            'siteOptions' => self::siteFilterOptions(),
            'company' => $request->integer('azienda') ? Company::find($request->integer('azienda'), ['id', 'name']) : null,
        ]);
    }

    /** @return array<string, string> */
    private static function siteFilterOptions(): array
    {
        return ['platform' => config('ksm.brand_name').' (sito principale)', 'company' => 'Siti delle aziende']
            + \App\Models\Domain::query()->orderBy('domain')->pluck('domain', 'id')->mapWithKeys(fn ($host, $id) => ["domain:$id" => $host])->all();
    }

    /**
     * Gli ordini filtrati come nell'elenco, in un CSV che Excel apre in
     * italiano: punto e virgola, virgola decimale, UTF-8 con BOM.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filters(Order::query()->with(['company', 'domain', 'items', 'payment', 'kmoneyPayment']), $request)->orderBy('id');
        $money = fn ($value) => number_format((float) $value, 2, ',', '');

        return response()->streamDownload(function () use ($query, $money) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Numero', 'Data', 'Stato', 'Sito', 'Azienda', 'Cliente', 'Email', 'Telefono', 'Indirizzo', 'Citta', 'CAP',
                'Provincia', 'Paese', 'Prodotti', 'Subtotale', 'Spedizione', 'Totale', 'Quota KMoney', 'Pagamento',
                'Corriere', 'Numero spedizione', 'Spedito il', 'Note del cliente', 'Note interne',
            ], ';');

            $query->chunkById(500, function ($orders) use ($out, $money) {
                foreach ($orders as $order) {
                    fputcsv($out, [
                        $order->reference,
                        $order->created_at?->format('d/m/Y H:i'),
                        $order->statusLabel(),
                        $order->siteLabel(),
                        $order->company?->name,
                        $order->billing_name,
                        $order->billing_email,
                        $order->billing_phone,
                        $order->billing_address,
                        $order->billing_city,
                        $order->billing_zip,
                        $order->billing_state,
                        $order->billing_country,
                        $order->items->map(fn ($item) => $item->quantity.' x '.$item->product_name)->implode(' | '),
                        $money($order->subtotal),
                        $money($order->shipping),
                        $money($order->total),
                        $money($order->kmoney_total),
                        $order->requiredPayments()->map(fn ($payment) => $payment->method.' '.$payment->status)->implode(', '),
                        $order->carrier,
                        $order->tracking_number,
                        $order->shipped_at?->format('d/m/Y'),
                        $order->notes,
                        $order->admin_notes,
                    ], ';');
                }
            });

            fclose($out);
        }, 'ordini-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** I filtri dell'elenco, gli stessi per la pagina, per "tutti i risultati" e per l'esportazione. */
    public function filters(Builder $query, Request $request): Builder
    {
        $term = trim($request->string('cerca')->toString());
        // "KSM-000123" o "123": si cerca anche per numero d'ordine.
        $number = (int) preg_replace('/\D/', '', $term);

        $site = $request->string('sito')->toString();

        return $query
            ->when($request->string('stato')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->integer('azienda'), fn ($q, $id) => $q->where('company_id', $id))
            ->when(trim($request->string('azienda_nome')->toString()), fn ($q, $name) => $q->whereHas(
                'company', fn ($c) => $c->where('name', 'like', '%'.addcslashes($name, '\\%_').'%')
            ))
            ->when($site === 'platform' || $site === 'company', fn ($q) => $q->where('site', $site))
            ->when(str_starts_with($site, 'domain:'), fn ($q) => $q->where('site', 'domain')->where('domain_id', (int) substr($site, 7)))
            ->when($this->date($request, 'dal'), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($this->date($request, 'al'), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('billing_name', 'like', "%$term%")
                ->orWhere('billing_email', 'like', "%$term%")
                ->when($number > 0, fn ($q) => $q->orWhere('id', $number))));
    }

    private function date(Request $request, string $key): ?\Illuminate\Support\Carbon
    {
        $value = $request->string($key)->toString();

        try {
            return $value !== '' ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Cambia lo stato o elimina piu' ordini insieme; eliminare vale solo sulle righe spuntate. */
    public function bulk(Request $request, OrderStatus $orderStatus): RedirectResponse
    {
        $request->validate(BulkSelection::rules(['status', 'delete'], onlySelected: ['delete']) + [
            'status' => ['required_if:action,status', 'nullable', 'in:'.implode(',', Order::STATUSES)],
        ], BulkSelection::messages());

        $query = BulkSelection::query($request, Order::query(), $this->filters(...));

        $message = match ($request->input('action')) {
            // Uno per uno: disponibilita' ed email seguono ogni ordine, come nella scheda.
            'status' => trans_choice(':count ordine segnato come «:status».|:count ordini segnati come «:status».',
                $this->changeEach($query, $orderStatus, $request->input('status')),
                ['status' => Order::STATUS_LABELS[$request->input('status')]]),
            'delete' => trans_choice(':count ordine eliminato.|:count ordini eliminati.', BulkSelection::deleteEach($query)),
        };

        return back()->with('success', $message);
    }

    /** Nuovo ordine a mano: prima l'azienda, poi il modulo con i suoi prodotti. */
    public function create(Request $request): View
    {
        $company = $request->integer('azienda') ? Company::find($request->integer('azienda')) : null;

        if (! $company) {
            $term = trim($request->string('cerca')->toString());

            return view('admin.orders.pick-company', [
                'term' => $term,
                'companies' => $term === '' ? collect() : Company::query()
                    ->where('name', 'like', '%'.addcslashes($term, '\\%_').'%')
                    ->orderBy('name')->take(20)->get(['id', 'name', 'city', 'is_active']),
            ]);
        }

        return view('admin.orders.form', $this->formData(new Order([
            'company_id' => $company->id, 'status' => 'paid', 'billing_country' => 'Italia', 'shipping' => 0,
        ]), $company));
    }

    public function store(Request $request, OrderEditor $editor): RedirectResponse
    {
        $data = $request->validate(OrderEditor::rules(creating: true));
        $order = $editor->create(Company::findOrFail($data['company_id']), $data);

        return redirect()->route('admin.orders.show', $order)->with('success', __('Ordine :ref creato.', ['ref' => $order->reference]));
    }

    public function edit(Order $order): View
    {
        abort_unless($order->company, 404);

        return view('admin.orders.form', $this->formData($order->load('items'), $order->company));
    }

    public function update(Request $request, Order $order, OrderEditor $editor): RedirectResponse
    {
        $editor->update($order, $request->validate(OrderEditor::rules(creating: false)));

        return redirect()->route('admin.orders.show', $order)->with('success', __('Ordine aggiornato.'));
    }

    private function formData(Order $order, Company $company): array
    {
        return [
            'order' => $order,
            'company' => $company,
            'products' => OrderEditor::productOptions($company),
            'sites' => OrderEditor::siteOptions($company),
            'blankRows' => OrderEditor::BLANK_ROWS,
        ];
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $order->load('items', 'company', 'user', 'payment', 'kmoneyPayment', 'domain'),
            'statuses' => Order::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, Order $order, OrderStatus $orderStatus): RedirectResponse
    {
        $data = $request->validate(OrderStatus::rules() + [
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $order->update(['admin_notes' => $data['admin_notes'] ?? null]);

        return back()->with(...$orderStatus->applyForm($order, $data, $request->boolean('notify')));
    }

    private function changeEach(Builder $query, OrderStatus $orderStatus, string $status): int
    {
        $count = 0;

        $query->clone()->where('status', '!=', $status)->chunkById(100, function ($orders) use ($orderStatus, $status, &$count) {
            foreach ($orders as $order) {
                $orderStatus->change($order, $status);
                $count++;
            }
        });

        return $count;
    }

    public function destroy(Order $order): RedirectResponse
    {
        $order->delete();

        return back()->with('success', __('Ordine eliminato.'));
    }
}
