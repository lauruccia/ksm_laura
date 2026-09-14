@extends('layouts.app')

@section('title', 'Ordine confermato')

@section('content')
    <section class="ksm-section">
        <div class="ksm-container" style="max-width: 720px;">
            <div class="ksm-card" style="padding: 28px;">
                <span class="ksm-badge">Pagamento ricevuto</span>
                <h1 style="font-size: 1.7rem; margin-top: 12px;">Grazie</h1>
                <p>Riferimento ordine <strong>{{ $order->reference }}</strong>.</p>

                @foreach ($order->requiredPayments() as $payment)
                    <p class="ksm-muted" style="font-size: .9rem; margin: 4px 0;">
                        @if ($order->hasKmoney())
                            {{ $payment->isKmoney() ? 'Quota KMoney pagata' : 'Parte in euro pagata' }}
                        @else
                            Pagato
                        @endif
                        con {{ \App\Payments\GatewayManager::label($payment->method) }}.
                        @if ($payment->transaction_id)
                            Riferimento del gestore: {{ $payment->transaction_id }}.
                        @endif
                    </p>
                @endforeach

                <div class="ksm-table-wrap" style="margin: 18px 0;">
                    <table class="ksm-table">
                        <tbody>
                        @foreach ($order->items as $item)
                            <tr>
                                <td>{{ $item->product_name }} &times; {{ $item->quantity }}</td>
                                <td style="text-align: right;">{{ \App\Support\Money::format($item->subtotal) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td>Spedizione</td>
                            <td style="text-align: right;">{{ \App\Support\Money::format($order->shipping) }}</td>
                        </tr>
                        <tr>
                            <td><strong>Totale</strong></td>
                            <td style="text-align: right;"><strong>{{ \App\Support\Money::format($order->total) }}</strong></td>
                        </tr>
                        @include('partials.order-kmoney-rows')
                        </tbody>
                    </table>
                </div>

                <a class="ksm-btn ksm-btn--primary" href="{{ route('orders.track') }}">Traccia ordine</a>
            </div>
        </div>
    </section>
@endsection
