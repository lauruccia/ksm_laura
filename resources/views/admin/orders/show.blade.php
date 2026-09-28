@extends('layouts.panel')

@section('title', 'Ordine '.$order->reference.' · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    @php($canManage = auth()->user()->can(\App\Support\Permissions::ORDERS_MANAGE))

    <div class="ksm-panel__head">
        <div>
            <h1>{{ $order->reference }}</h1>
            <p class="ksm-panel__lead">
                {{ $order->created_at?->format('d/m/Y H:i') }} · da {{ $order->siteLabel() }}
                <span class="ksm-badge ksm-badge--{{ $order->status }}" style="margin-left: 6px;">{{ $order->statusLabel() }}</span>
            </p>
        </div>
        <div class="ksm-rowactions">
            @if ($canManage)
                <a class="ksm-btn ksm-btn--primary" href="{{ route('admin.orders.edit', $order) }}">Modifica ordine</a>
            @endif
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.orders.index') }}">Torna agli ordini</a>
        </div>
    </div>

    <div class="ksm-grid ksm-grid--2">
        <section class="ksm-card" style="padding: 20px;">
            <h2 style="font-size: 1.05rem;">Righe</h2>
            <table class="ksm-table">
                <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>{{ $item->product_name }} &times; {{ $item->quantity }}
                            <span class="ksm-muted">({{ \App\Support\Money::format($item->product_price) }} cad.)</span></td>
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

            <h2 style="font-size: 1.05rem; margin-top: 22px;">Cliente</h2>
            <ul class="ksm-meta">
                <li>{{ $order->billing_name }}</li>
                <li>{{ $order->billing_email }} @if ($order->billing_phone) · {{ $order->billing_phone }} @endif</li>
                <li>{{ $order->billing_address }}, {{ $order->billing_zip }} {{ $order->billing_city }} {{ $order->billing_state }} {{ $order->billing_country }}</li>
                <li>Account: {{ $order->user ? $order->user->email : 'nessuno (ordine inserito a mano)' }}</li>
                @if ($order->notes)
                    <li>Note del cliente: {{ $order->notes }}</li>
                @endif
            </ul>

            <h2 style="font-size: 1.05rem; margin-top: 22px;">Azienda e pagamento</h2>
            <ul class="ksm-meta">
                <li>Azienda: {{ $order->company?->name }}</li>
                @forelse ($order->requiredPayments() as $payment)
                    <li>{{ $payment->isKmoney() ? 'Quota KMoney' : 'Pagamento' }}: {{ $payment->method }} · {{ $payment->status }}</li>
                @empty
                    <li>Nessun pagamento online collegato.</li>
                @endforelse
                <li>Disponibilità dei prodotti: {{ $order->stock_deducted_at ? 'scalata il '.$order->stock_deducted_at->format('d/m/Y') : 'non scalata' }}</li>
            </ul>
        </section>

        <section class="ksm-card" style="padding: 20px;">
            <h2 style="font-size: 1.05rem;">Stato e spedizione</h2>

            @if ($canManage)
                @include('orders._status-form', ['action' => route('admin.orders.status', $order), 'withNotes' => true])
            @else
                <ul class="ksm-meta">
                    <li>Stato: {{ $order->statusLabel() }}</li>
                    <li>Corriere: {{ $order->carrier ?: '—' }} · n. {{ $order->tracking_number ?: '—' }}</li>
                    @if ($order->admin_notes)<li>Note interne: {{ $order->admin_notes }}</li>@endif
                </ul>
            @endif
        </section>
    </div>
@endsection
