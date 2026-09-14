@php use App\Support\Permissions as P; @endphp

@extends('layouts.panel')

@section('title', 'Riepilogo · KSM')
@section('role', 'Amministrazione')
@section('crumb', 'Riepilogo')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <div>
            <h1>Riepilogo</h1>
            <p class="ksm-muted" style="margin: 4px 0 0;">
                Ciao {{ auth()->user()->name }}, ecco come sta andando {{ $settings->website_name ?? 'il marketplace' }}.
            </p>
        </div>
        <span class="ksm-badge ksm-badge--muted">{{ now()->translatedFormat('j F Y') }}</span>
    </div>

    @if ($cards)
        <div class="ksm-kpis">
            @foreach ($cards as $card)
                <a class="ksm-kpi ksm-kpi--{{ $card['tone'] }}" href="{{ $card['url'] }}">
                    <span class="ksm-kpi__icon"><x-icon :name="$card['icon']" :size="20" /></span>
                    <span class="ksm-kpi__value">{{ $card['value'] }}</span>
                    <span class="ksm-kpi__label">{{ $card['label'] }}</span>

                    @isset($card['note'])
                        <span class="ksm-kpi__note">{{ $card['note'] }}</span>
                    @endisset

                    @isset($card['ratio'])
                        <span class="ksm-kpi__bar"><i style="width: {{ round($card['ratio'] * 100) }}%"></i></span>
                    @endisset
                </a>
            @endforeach
        </div>
    @endif

    <div class="ksm-panel-grid">
        @if ($chart)
            <section class="ksm-box">
                <div class="ksm-box__head">
                    <h2>Andamento degli ultimi dodici mesi</h2>
                    <span class="ksm-legend">
                        <i class="ksm-legend__key ksm-legend__key--bar"></i> incassato
                        <i class="ksm-legend__key ksm-legend__key--line"></i> ordini
                    </span>
                </div>

                @include('admin.partials.chart', ['chart' => $chart])
            </section>
        @endif

        @if ($subscriptions)
            <section class="ksm-box">
                <div class="ksm-box__head">
                    <h2>Abbonamenti</h2>
                    <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.subscriptions.index') }}">Apri</a>
                </div>

                <div class="ksm-minitiles">
                    <div><strong>{{ $subscriptions['active'] }}</strong><span>attivi</span></div>
                    <div><strong>{{ $subscriptions['pending'] }}</strong><span>in attesa</span></div>
                    <div><strong>{{ $subscriptions['expiring'] }}</strong><span>in scadenza</span></div>
                </div>

                <ul class="ksm-meterlist">
                    @foreach ($subscriptions['plans'] as $plan)
                        <li>
                            <span class="ksm-meterlist__top">
                                <span>{{ $plan['name'] }}</span>
                                <span class="ksm-muted">{{ $plan['count'] }}</span>
                            </span>
                            <span class="ksm-meter"><i style="width: {{ round($plan['ratio'] * 100) }}%"></i></span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    @if ($orderStates)
        <section class="ksm-box">
            <div class="ksm-box__head">
                <h2>Ordini per stato</h2>
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.orders.index') }}">Tutti gli ordini</a>
            </div>

            <div class="ksm-statebar">
                @foreach ($orderStates as $state)
                    <div class="ksm-statebar__item ksm-statebar__item--{{ $state['status'] }}">
                        <strong>{{ $state['count'] }}</strong>
                        <span>{{ $state['label'] }}</span>
                        <span class="ksm-meter"><i style="width: {{ round($state['ratio'] * 100) }}%"></i></span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($pendingSubscriptions && $pendingSubscriptions->isNotEmpty())
        <section class="ksm-box">
            <div class="ksm-box__head">
                <h2>Quote da confermare</h2>
            </div>

            <div class="ksm-table-wrap">
                <table class="ksm-table">
                    <thead><tr><th>Azienda</th><th>Piano</th><th>Metodo</th><th>Importo</th><th>Richiesto</th></tr></thead>
                    <tbody>
                    @foreach ($pendingSubscriptions as $subscription)
                        <tr>
                            <td>{{ $subscription->company?->name }}</td>
                            <td>{{ $subscription->plan?->name }}</td>
                            <td class="ksm-muted">{{ $subscription->payment_method }}</td>
                            <td>{{ \App\Support\Money::format($subscription->price) }}</td>
                            <td class="ksm-muted">{{ $subscription->created_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="ksm-panel-grid">
        @if ($latestOrders)
            <section class="ksm-box">
                <div class="ksm-box__head"><h2>Ultimi ordini</h2></div>

                <div class="ksm-table-wrap">
                    <table class="ksm-table">
                        <thead><tr><th>Ordine</th><th>Azienda</th><th>Totale</th><th>Stato</th></tr></thead>
                        <tbody>
                        @forelse ($latestOrders as $order)
                            <tr>
                                <td><a href="{{ route('admin.orders.show', $order) }}">{{ $order->reference }}</a></td>
                                <td>{{ $order->company?->name }}</td>
                                <td>{{ \App\Support\Money::format($order->total) }}</td>
                                <td><span class="ksm-badge ksm-badge--{{ $order->status }}">{{ $order->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="ksm-muted">Nessun ordine.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($latestCompanies)
            <section class="ksm-box">
                <div class="ksm-box__head"><h2>Ultime aziende</h2></div>

                <div class="ksm-table-wrap">
                    <table class="ksm-table">
                        <thead><tr><th>Nome</th><th>Città</th><th>Piano</th><th>Attiva</th></tr></thead>
                        <tbody>
                        @forelse ($latestCompanies as $company)
                            <tr>
                                <td>
                                    @can(P::COMPANIES_MANAGE)
                                        <a href="{{ route('admin.companies.edit', $company) }}">{{ $company->name }}</a>
                                    @else
                                        {{ $company->name }}
                                    @endcan
                                </td>
                                <td>{{ $company->city }}</td>
                                <td class="ksm-muted">{{ $company->plan?->name ?? 'nessuno' }}</td>
                                <td>
                                    <span class="ksm-badge @if (! $company->is_active) ksm-badge--muted @endif">
                                        {{ $company->is_active ? 'Si' : 'No' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="ksm-muted">Nessuna azienda.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection
