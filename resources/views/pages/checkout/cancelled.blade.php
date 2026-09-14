@extends('layouts.app')

@section('title', 'Pagamento non completato')

@section('content')
    @php
        $kmoneyPaid = $order->kmoneyPayment?->status === 'completed';
        $next = $order->status === 'pending' ? $order->pendingPayment() : null;
    @endphp

    <section class="ksm-section">
        <div class="ksm-container" style="max-width: 640px;">
            <div class="ksm-card" style="padding: 28px;">
                <h1 style="font-size: 1.6rem;">Pagamento non completato</h1>

                @if ($kmoneyPaid)
                    <p>
                        Per l'ordine {{ $order->reference }} la quota KMoney di
                        {{ number_format((float) $order->kmoney_total, 2, ',', '.') }} KY è già pagata.
                        Manca la parte in euro di {{ \App\Support\Money::format($order->euro_total) }}.
                    </p>
                    <p class="ksm-muted" style="font-size: .9rem;">
                        Se non vuoi completare l'ordine, chiedi al venditore il rimborso dei KY:
                        lo fa lui dal suo conto KMoney.
                    </p>
                @else
                    <p>L'ordine {{ $order->reference }} resta in attesa e non è stato addebitato nulla.</p>
                @endif

                @if ($next && ($next->isKmoney() || $methods))
                    <form method="POST" action="{{ route('checkout.retry', $order) }}"
                          style="display: flex; gap: 10px; flex-wrap: wrap; align-items: end; margin: 18px 0;">
                        @csrf

                        @unless ($next->isKmoney())
                            <div class="ksm-field" style="margin: 0; min-width: 220px;">
                                <label class="ksm-label" for="method">Paga la parte in euro con</label>
                                <select class="ksm-select" id="method" name="method" required>
                                    @foreach ($methods as $method)
                                        <option value="{{ $method }}" @selected($next->method === $method)>
                                            {{ \App\Payments\GatewayManager::label($method) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endunless

                        <button class="ksm-btn ksm-btn--primary" type="submit">
                            {{ $next->isKmoney() ? 'Riprova il pagamento KMoney' : 'Riprova il pagamento' }}
                        </button>
                    </form>
                @endif

                <a class="ksm-btn ksm-btn--ghost" href="{{ route('products.index') }}">Torna ai prodotti</a>
            </div>
        </div>
    </section>
@endsection
