@extends('layouts.panel')

@section('title', 'Il mio account · '.$tenant->brandName())
@section('role', 'Il mio account')
@section('crumb', 'Riepilogo')
@section('nav')@include('account.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <div>
            <h1>Ciao {{ $user->name }}</h1>
            <p class="ksm-muted" style="margin: 4px 0 0;">Qui trovi i tuoi ordini e i dati che usiamo in cassa.</p>
        </div>
        <a class="ksm-btn ksm-btn--primary" href="{{ route('products.index') }}">Continua a comprare</a>
    </div>

    <div class="ksm-kpis">
        <div class="ksm-kpi ksm-kpi--dark">
            <span class="ksm-kpi__icon"><x-icon name="cart" :size="20" /></span>
            <span class="ksm-kpi__value">{{ $orderCount }}</span>
            <span class="ksm-kpi__label">Ordini</span>
        </div>

        <div class="ksm-kpi ksm-kpi--accent">
            <span class="ksm-kpi__icon"><x-icon name="box" :size="20" /></span>
            <span class="ksm-kpi__value">{{ $openCount }}</span>
            <span class="ksm-kpi__label">In corso</span>
            <span class="ksm-kpi__note">non ancora conclusi</span>
        </div>

        <div class="ksm-kpi ksm-kpi--money">
            <span class="ksm-kpi__icon"><x-icon name="chart" :size="20" /></span>
            <span class="ksm-kpi__value">{{ \App\Support\Money::format($spent) }}</span>
            <span class="ksm-kpi__label">Speso</span>
            <span class="ksm-kpi__note">ordini pagati</span>
        </div>
    </div>

    <section class="ksm-box">
        <div class="ksm-box__head">
            <h2>Ultimi ordini</h2>
            <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('account.orders.index') }}">Vedi tutti</a>
        </div>

        <div class="ksm-table-wrap">
            <table class="ksm-table">
                <thead><tr><th>Ordine</th><th>Azienda</th><th>Totale</th><th>Stato</th><th></th></tr></thead>
                <tbody>
                @forelse ($latestOrders as $order)
                    <tr>
                        <td><a href="{{ route('account.orders.show', $order) }}">{{ $order->reference }}</a></td>
                        <td>{{ $order->company?->name }}</td>
                        <td>{{ \App\Support\Money::format($order->total) }}</td>
                        <td><span class="ksm-badge ksm-badge--{{ $order->status }}">{{ $order->status }}</span></td>
                        <td class="ksm-rowactions">
                            <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('account.orders.show', $order) }}">Apri</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="ksm-muted">Non hai ancora ordini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="ksm-box">
        <div class="ksm-box__head">
            <h2>Indirizzo abituale</h2>
            <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('account.profile.edit') }}">Modifica</a>
        </div>

        @if ($user->billing_address)
            <p style="margin: 0;">
                {{ $user->name }}<br>
                {{ $user->billing_address }}<br>
                {{ $user->billing_zip }} {{ $user->billing_city }}
                @if ($user->billing_state) ({{ $user->billing_state }}) @endif<br>
                {{ $user->billing_country }}
            </p>
        @else
            <p class="ksm-muted" style="margin: 0;">
                Non hai ancora salvato un indirizzo. Se lo aggiungi, la cassa lo compila da sola.
            </p>
        @endif
    </section>
@endsection
