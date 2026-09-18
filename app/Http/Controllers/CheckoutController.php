<?php

namespace App\Http\Controllers;

use App\Models\AdminPaymentSetting;
use App\Models\AdminSetting;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Payments\GatewayManager;
use App\Payments\KMoney\CheckoutSplit;
use App\Payments\KMoney\KMoneySplitter;
use App\Payments\PaymentCompletion;
use App\Payments\PaymentException;
use App\Support\Cart;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * La cassa.
 *
 * Un ordine si paga in una o due parti: prima la quota KMoney, sul sito
 * KMoney, poi il resto in euro con il gestore scelto. Al rientro da una
 * parte si apre subito l'altra. L'esito di ogni parte si chiede al suo
 * gestore, mai ai parametri dell'indirizzo.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly Cart $cart,
        private readonly GatewayManager $gateways,
        private readonly PaymentCompletion $completion,
        private readonly KMoneySplitter $splitter,
    ) {
    }

    public function show(): View|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $this->cart->refresh();
        $company = Company::with('paymentSettings')->findOrFail($this->cart->companyId());
        $shipping = $this->shippingFor($company);

        return view('pages.checkout.show', [
            // Da ospiti la cassa mostra accesso e registrazione al posto dei
            // dati di fatturazione: l'indirizzo salvato, se c'e', arriva dopo.
            'defaults' => auth()->user()?->billingDefaults() ?? [],
            'items' => $this->cart->items(),
            'subtotal' => $this->cart->subtotal(),
            'shipping' => $shipping,
            'company' => $company,
            'methods' => $this->gateways->availableFor($company->paymentSettings),
            'split' => $this->splitter->split($company, $this->cart->items(), $shipping),
            'kmoneyAvailable' => $this->gateways->kmoneyAvailable($company->paymentSettings),
            'euroFallback' => $this->euroFallbackAllowed($company),
        ]);
    }

    public function process(Request $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $company = Company::with('paymentSettings')->findOrFail($this->cart->companyId());
        $this->cart->refresh();
        $items = $this->cart->items();
        $shipping = $this->shippingFor($company);
        $methods = $this->gateways->availableFor($company->paymentSettings);
        $split = $this->splitter->split($company, $items, $shipping);

        $data = $request->validate([
            'billing_name' => ['required', 'string', 'max:255'],
            'billing_email' => ['required', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['required', 'string', 'max:500'],
            'billing_city' => ['required', 'string', 'max:120'],
            'billing_state' => ['nullable', 'string', 'max:120'],
            'billing_zip' => ['required', 'string', 'max:20'],
            'billing_country' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'salva_dati' => ['nullable', 'boolean'],
            'method' => ['nullable', 'string', Rule::in($methods)],
            'pagamento_kmoney' => [Rule::requiredIf($split->hasKmoney()), 'nullable', Rule::in(['conto', 'euro'])],
        ]);

        if ($split->hasKmoney() && ($data['pagamento_kmoney'] ?? null) === 'euro') {
            if (! $this->euroFallbackAllowed($company)) {
                throw ValidationException::withMessages(['pagamento_kmoney' => $split->vendorInDebt
                    ? __('Questo venditore accetta questi prodotti solo in KMoney.')
                    : __('Per questi prodotti serve un conto KMoney.')]);
            }

            $split = $this->splitter->split($company, $items, $shipping, allEuro: true);
        }

        if ($split->hasKmoney() && ! $this->gateways->kmoneyAvailable($company->paymentSettings)) {
            return back()->withInput()->with('error', __(
                'Il venditore non ha ancora collegato il suo conto KMoney: per ora questi prodotti non si possono comprare.'
            ));
        }

        if ($split->hasEuro() && blank($data['method'] ?? null)) {
            throw ValidationException::withMessages(['method' => __('Scegli come pagare la parte in euro.')]);
        }

        // Come nelle casse piu' usate: l'indirizzo si salva nell'account solo se
        // l'acquirente lo chiede, e il prossimo ordine parte gia' compilato.
        if ($request->boolean('salva_dati')) {
            $request->user()->update(collect($data)->only([
                'billing_address', 'billing_city', 'billing_state', 'billing_zip', 'billing_country',
            ])->all());
        }

        $order = $this->createOrder(collect($data)->except('salva_dati')->all(), $company, $items, $shipping, $split);
        $first = $order->pendingPayment();

        try {
            $redirectUrl = $this->start($order, $first);
        } catch (PaymentException $e) {
            Log::warning('Avvio pagamento non riuscito', ['order' => $order->id, 'error' => $e->getMessage()]);
            $this->completion->markFailed($first, ['error' => $e->getMessage()]);

            // Il carrello resta pieno: l'acquirente puo' riprovare subito.
            return back()->withInput()
                ->with('error', __('Non siamo riusciti ad aprire il pagamento. Riprova o scegli un altro metodo.'));
        }

        $this->cart->clear();

        return redirect()->away($redirectUrl);
    }

    /** Rientro da un gestore: si verifica la parte appena pagata e si passa alla successiva. */
    public function returnFromGateway(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($order);

        $payment = $order->pendingPayment();

        if (! $payment) {
            return $order->requiredPayments()->isEmpty()
                ? redirect()->route('checkout.cancelled', $order)
                : redirect()->route('checkout.success', $order);
        }

        // Parte mai aperta: la precedente l'ha chiusa una notifica prima che
        // l'acquirente tornasse. Non c'e' niente da verificare, si apre.
        if (blank($payment->transaction_id) && ! $payment->is($order->requiredPayments()->first())) {
            return $this->continueWith($order, $payment);
        }

        try {
            $result = $this->confirm($order, $payment, $request);
        } catch (PaymentException $e) {
            Log::error('Verifica pagamento non riuscita', ['order' => $order->id, 'error' => $e->getMessage()]);

            return redirect()->route('checkout.cancelled', $order)
                ->with('error', __('Non siamo riusciti a verificare il pagamento. Contatta il venditore prima di riprovare.'));
        }

        if (! $result['paid']) {
            $this->completion->markFailed($payment, $result['response']);

            return redirect()->route('checkout.cancelled', $order)
                ->with('error', __('Il pagamento non risulta completato.'));
        }

        $this->completion->markPaid($order, $payment, $result['reference'], $result['response']);

        return $this->afterPayment($order);
    }

    /**
     * Nuovo tentativo sul pagamento che manca.
     *
     * Serve soprattutto con la quota KMoney gia' pagata: l'acquirente sceglie
     * di nuovo come pagare la parte in euro. I KY gia' incassati restano; un
     * eventuale rimborso lo fa il venditore dal suo conto KMoney.
     */
    public function retry(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($order);
        abort_unless($order->status === 'pending', 404);

        $payment = $order->pendingPayment();

        if (! $payment) {
            return redirect()->route('checkout.success', $order);
        }

        $method = $payment->method;

        if (! $payment->isKmoney()) {
            $method = $request->validate([
                'method' => ['required', Rule::in($this->gateways->availableFor($order->company->paymentSettings))],
            ])['method'];
        }

        // Prima di ripartire si guarda se il tentativo precedente, in realta', e' andato:
        // ricominciare da capo farebbe pagare due volte.
        if (filled($payment->transaction_id)) {
            try {
                $result = $this->confirm($order, $payment, $request);
            } catch (PaymentException) {
                $result = ['paid' => false];
            }

            if ($result['paid']) {
                $this->completion->markPaid($order, $payment, $result['reference'], $result['response']);

                return $this->afterPayment($order);
            }
        }

        $payment->update(['method' => $method, 'status' => 'pending', 'transaction_id' => null, 'response' => null]);

        return $this->continueWith($order, $payment);
    }

    public function success(Order $order): View
    {
        $this->authorizeOrder($order);

        return view('pages.checkout.success', ['order' => $order->load('items', 'payment', 'kmoneyPayment')]);
    }

    public function cancelled(Order $order): View
    {
        $this->authorizeOrder($order);

        return view('pages.checkout.cancelled', [
            'order' => $order->load('payment', 'kmoneyPayment', 'company.paymentSettings'),
            'methods' => $this->gateways->availableFor($order->company->paymentSettings),
        ]);
    }

    /** Dopo una parte pagata: la successiva, oppure l'esito. */
    private function afterPayment(Order $order): RedirectResponse
    {
        $next = $order->fresh()->pendingPayment();

        return $next
            ? $this->continueWith($order, $next)
            : redirect()->route('checkout.success', $order);
    }

    /** Porta l'acquirente al pagamento che manca. */
    private function continueWith(Order $order, Payment $payment): RedirectResponse
    {
        try {
            return redirect()->away($this->start($order, $payment));
        } catch (PaymentException $e) {
            Log::warning('Apertura della parte successiva non riuscita', [
                'order' => $order->id,
                'payment' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            $this->completion->markFailed($payment, ['error' => $e->getMessage()]);

            return redirect()->route('checkout.cancelled', $order)
                ->with('error', __('Non siamo riusciti ad aprire il pagamento che manca. Riprova da qui.'));
        }
    }

    private function start(Order $order, Payment $payment): string
    {
        return $this->gateways
            ->driver($payment->method, $order->company->paymentSettings)
            ->start($order, $payment);
    }

    private function confirm(Order $order, Payment $payment, Request $request): array
    {
        return $this->gateways
            ->driver($payment->method, $order->company->paymentSettings)
            ->confirm($order, $payment, $request);
    }

    private function createOrder(array $data, Company $company, Collection $items, float $shipping, CheckoutSplit $split): Order
    {
        $subtotal = (float) $items->sum(fn ($item) => $item['price'] * $item['quantity']);
        $mode = $company->paymentSettings?->mode ?? 'test';

        return DB::transaction(function () use ($data, $company, $items, $shipping, $split, $subtotal, $mode) {
            $payment = fn (string $method, float $amount, string $currency) => Payment::create([
                'user_id' => auth()->id(),
                'company_id' => $company->id,
                'method' => $method,
                'mode' => $mode,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'pending',
            ]);

            $euro = $split->hasEuro() ? $payment($data['method'], $split->euro(), config('ksm.currency')) : null;
            $kmoney = $split->hasKmoney() ? $payment('kmoney', $split->kmoney(), 'KY') : null;

            $order = Order::create(collect($data)->except(['method', 'pagamento_kmoney'])->all() + [
                'user_id' => auth()->id(),
                'company_id' => $company->id,
                'payment_id' => $euro?->id,
                'kmoney_payment_id' => $kmoney?->id,
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'total' => $subtotal + $shipping,
                'kmoney_total' => $split->kmoney(),
                'currency' => config('ksm.currency'),
                'status' => 'pending',
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['variant_id'] ?? null,
                    'product_name' => $item['name'],
                    'product_price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['price'] * $item['quantity'],
                    'kmoney_percent' => $split->percentFor((int) $item['product_id']),
                    'kmoney_amount' => intdiv(\App\Payments\KMoney\KMoneySplitter::cents($item['price'] * $item['quantity']) * $split->percentFor((int) $item['product_id']), 100) / 100,
                ]);
            }

            return $order->load('items', 'payment', 'kmoneyPayment');
        });
    }

    /** Tutto in euro per chi non ha KMoney: se l'amministrazione lo consente e il venditore non e' in debito. */
    private function euroFallbackAllowed(Company $company): bool
    {
        return AdminPaymentSetting::current()->kmoney_euro_fallback
            && ! $company->paymentSettings?->kmoney_in_debt;
    }

    private function shippingFor(Company $company): float
    {
        return (float) ($company->base_shipping_rate ?? AdminSetting::current()->base_shipping_rate ?? 0);
    }

    private function authorizeOrder(Order $order): void
    {
        abort_unless($order->user_id === auth()->id(), 403);
    }
}
