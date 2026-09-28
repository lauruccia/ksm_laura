@extends('layouts.panel')

@section('title', 'Ordine · KSM')
@section('role', 'Area azienda')
@section('nav')@include('vendor.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>{{ $order->reference }}</h1>
        <a class="ksm-btn ksm-btn--ghost" href="{{ route('vendor.orders.index') }}">Torna agli ordini</a>
    </div>

    <div class="ksm-grid ksm-grid--2">
        <section class="ksm-card" style="padding: 20px;">
            <h2 style="font-size: 1.05rem;">Righe</h2>
            <table class="ksm-table">
                <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->product_name }} &times; {{ $item->quantity }}</td>
                        <td style="text-align: right;">{{ \App\Support\Money::format($item->subtotal) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td><strong>Totale</strong></td>
                    <td style="text-align: right;"><strong>{{ \App\Support\Money::format($order->total) }}</strong></td>
                </tr>
                @include('partials.order-kmoney-rows')
                </tbody>
            </table>
        </section>

        <section class="ksm-card" style="padding: 20px;">
            <h2 style="font-size: 1.05rem;">Spedizione</h2>
            <ul class="ksm-meta">
                <li>{{ $order->billing_name }}</li>
                <li>{{ $order->billing_address }}</li>
                <li>{{ $order->billing_zip }} {{ $order->billing_city }} {{ $order->billing_country }}</li>
                <li>{{ $order->billing_email }}</li>
                <li>{{ $order->billing_phone }}</li>
            </ul>

            @if ($order->notes)
                <p style="margin: 12px 0 0;"><strong>Note del cliente:</strong> {{ $order->notes }}</p>
            @endif

            @include('orders._status-form', ['action' => route('vendor.orders.status', $order)])
        </section>
    </div>
@endsection
