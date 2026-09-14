@php
    // Il percorso di un ordine, dal pagamento alla consegna. Un ordine
    // annullato non ha tappe da mostrare: si dice e basta.
    $steps = ['pending' => 'Ricevuto', 'paid' => 'Pagato', 'shipped' => 'Spedito', 'completed' => 'Concluso'];
    $reached = array_search($order->status, array_keys($steps), true);
@endphp

@extends('layouts.panel')

@section('title', $order->reference.' · KSM')
@section('role', 'Il mio account')
@section('crumb', 'Ordine '.$order->reference)
@section('nav')@include('account.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <div>
            <h1>{{ $order->reference }}</h1>
            <p class="ksm-muted" style="margin: 4px 0 0;">
                Ordinato il {{ $order->created_at?->translatedFormat('j F Y') }}
                @if ($order->company) presso {{ $order->company->name }} @endif
            </p>
        </div>
        <a class="ksm-btn ksm-btn--ghost" href="{{ route('account.orders.index') }}">Torna agli ordini</a>
    </div>

    <section class="ksm-box">
        @if ($order->status === 'cancelled')
            <p style="margin: 0;"><span class="ksm-badge ksm-badge--cancelled">Ordine annullato</span></p>
        @else
            <ol class="ksm-steps">
                @foreach ($steps as $key => $label)
                    <li class="ksm-steps__item @if ($reached !== false && $loop->index <= $reached) is-done @endif">
                        <span class="ksm-steps__dot"></span>
                        <span>{{ $label }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    <div class="ksm-panel-grid">
        <section class="ksm-box">
            <div class="ksm-box__head"><h2>Cosa hai ordinato</h2></div>

            <div class="ksm-table-wrap">
                <table class="ksm-table">
                    <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>
                                @if ($item->product)
                                    <a href="{{ route('products.show', $item->product->slug) }}">{{ $item->product_name }}</a>
                                @else
                                    {{ $item->product_name }}
                                @endif
                                <span class="ksm-muted">&times; {{ $item->quantity }}</span>
                            </td>
                            <td style="text-align: right;">{{ \App\Support\Money::format($item->subtotal) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td class="ksm-muted">Spedizione</td>
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
        </section>

        <section class="ksm-box">
            <div class="ksm-box__head"><h2>Consegna e pagamento</h2></div>

            <p style="margin: 0 0 14px;">
                {{ $order->billing_name }}<br>
                {{ $order->billing_address }}<br>
                {{ $order->billing_zip }} {{ $order->billing_city }}
                @if ($order->billing_state) ({{ $order->billing_state }}) @endif<br>
                {{ $order->billing_country }}
            </p>

            <ul class="ksm-meta">
                <li><x-icon name="mail" :size="16" /> <span>{{ $order->billing_email }}</span></li>
                @if ($order->billing_phone)
                    <li><x-icon name="phone" :size="16" /> <span>{{ $order->billing_phone }}</span></li>
                @endif
                @foreach ($order->requiredPayments() as $payment)
                    <li>
                        <x-icon name="check" :size="16" />
                        <span>{{ $payment->isKmoney() ? 'Quota KMoney: ' : '' }}{{ $payment->method }} · {{ $payment->status }}</span>
                    </li>
                @endforeach
            </ul>

            @if ($order->company)
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" style="margin-top: 14px;"
                   href="{{ route('companies.show', $order->company->slug) }}">Vai all'azienda</a>
            @endif
        </section>
    </div>
@endsection
