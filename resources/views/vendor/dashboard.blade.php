@extends('layouts.panel')

@section('title', $company->name.' · KSM')
@section('role', 'Area azienda')
@section('crumb', 'Riepilogo')
@section('me', $company->name)
@section('nav')@include('vendor.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <div>
            <h1>{{ $company->name }}</h1>
            <p class="ksm-muted" style="margin: 4px 0 0;">
                {{ $company->city }}@if ($company->category) · {{ $company->category->name }} @endif
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('companies.show', $company->slug) }}">Vedi vetrina</a>
            <a class="ksm-btn ksm-btn--primary" href="{{ route('vendor.profile.edit') }}">Modifica profilo</a>
        </div>
    </div>

    @if ($pendingSubscription)
        <div class="ksm-alert ksm-alert--error">
            C e un pagamento in attesa per il piano {{ $pendingSubscription->plan?->name }}.
            Finche non risulta incassato il profilo resta spento.
            <a href="{{ route('subscription.index') }}">Vai al piano</a>
        </div>
    @endif

    <div class="ksm-kpis">
        <div class="ksm-kpi ksm-kpi--{{ $company->is_active ? 'accent' : 'muted' }}">
            <span class="ksm-kpi__icon"><x-icon name="{{ $company->is_active ? 'check' : 'shield' }}" :size="20" /></span>
            <span class="ksm-kpi__value">{{ $company->is_active ? 'Attivo' : 'Spento' }}</span>
            <span class="ksm-kpi__label">Profilo</span>
            <span class="ksm-kpi__note">
                {{ $company->is_active ? 'visibile nella directory' : 'non compare nella directory' }}
            </span>
        </div>

        @if ($sells)
            <div class="ksm-kpi ksm-kpi--dark">
                <span class="ksm-kpi__icon"><x-icon name="box" :size="20" /></span>
                <span class="ksm-kpi__value">{{ $productCount }}</span>
                <span class="ksm-kpi__label">Prodotti</span>
                <span class="ksm-kpi__note">{{ $activeProducts }} in vendita</span>
                <span class="ksm-kpi__bar">
                    <i style="width: {{ $productCount ? round($activeProducts / $productCount * 100) : 0 }}%"></i>
                </span>
            </div>

            <div class="ksm-kpi ksm-kpi--dark">
                <span class="ksm-kpi__icon"><x-icon name="cart" :size="20" /></span>
                <span class="ksm-kpi__value">{{ $orderCount }}</span>
                <span class="ksm-kpi__label">Ordini</span>
                <span class="ksm-kpi__note">{{ $pending }} da evadere</span>
            </div>

            <div class="ksm-kpi ksm-kpi--money">
                <span class="ksm-kpi__icon"><x-icon name="chart" :size="20" /></span>
                <span class="ksm-kpi__value">{{ \App\Support\Money::format($revenue) }}</span>
                <span class="ksm-kpi__label">Incassato</span>
                <span class="ksm-kpi__note">ordini pagati</span>
            </div>
        @endif

        <div class="ksm-kpi ksm-kpi--muted">
            <span class="ksm-kpi__icon"><x-icon name="users" :size="20" /></span>
            <span class="ksm-kpi__value">{{ $reviewCount }}</span>
            <span class="ksm-kpi__label">Recensioni</span>
            @if ($reviewCount)
                <span class="ksm-kpi__note">media {{ $company->average_rating }} su 5</span>
            @endif
        </div>
    </div>

    <div class="ksm-panel-grid">
        <section class="ksm-box">
            <div class="ksm-box__head">
                <h2>Il tuo piano</h2>
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('subscription.index') }}">
                    {{ $subscription ? 'Cambia piano' : 'Scegli un piano' }}
                </a>
            </div>

            @if ($subscription)
                <p class="ksm-planline">
                    <strong>{{ $subscription->plan?->name }}</strong>
                    <span class="ksm-badge">{{ $subscription->statusLabel() }}</span>
                </p>

                <ul class="ksm-meta" style="margin-bottom: 14px;">
                    <li>
                        <x-icon name="check" :size="16" />
                        <span>Quota {{ \App\Support\Money::format($subscription->price) }}</span>
                    </li>
                    @if ($subscription->ends_at)
                        <li>
                            <x-icon name="sparkle" :size="16" />
                            <span>
                                Scade il {{ $subscription->ends_at->translatedFormat('j F Y') }},
                                fra {{ $subscription->daysLeft() }} giorni
                            </span>
                        </li>
                    @endif
                </ul>

                <p class="ksm-muted" style="margin: 0 0 8px;">Cosa comprende:</p>
                <ul class="ksm-taglist">
                    @foreach ($company->plan?->capabilityLabels() ?? [] as $label)
                        <li>{{ $label }}</li>
                    @endforeach
                </ul>
            @else
                <p class="ksm-muted" style="margin: 0;">
                    Non hai un piano in corso, quindi il profilo non e visibile.
                    Scegline uno per accendere la scheda.
                </p>
            @endif
        </section>

        <section class="ksm-box">
            <div class="ksm-box__head"><h2>Da sistemare</h2></div>

            @forelse ($todo as $item)
                <a class="ksm-todo" href="{{ $item['url'] }}">
                    <x-icon name="arrow" :size="16" />
                    <span>{{ $item['text'] }}</span>
                </a>
            @empty
                <p class="ksm-muted" style="margin: 0;">
                    Nulla in sospeso: la scheda e completa.
                </p>
            @endforelse
        </section>
    </div>

    @if ($sells)
        <section class="ksm-box">
            <div class="ksm-box__head">
                <h2>Ultimi ordini</h2>
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('vendor.orders.index') }}">Tutti gli ordini</a>
            </div>

            <div class="ksm-table-wrap">
                <table class="ksm-table">
                    <thead><tr><th>Ordine</th><th>Cliente</th><th>Totale</th><th>Stato</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($latestOrders as $order)
                        <tr>
                            <td>{{ $order->reference }}</td>
                            <td>{{ $order->billing_name }}</td>
                            <td>{{ \App\Support\Money::format($order->total) }}</td>
                            <td><span class="ksm-badge ksm-badge--{{ $order->status }}">{{ $order->status }}</span></td>
                            <td class="ksm-rowactions">
                                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('vendor.orders.show', $order) }}">Apri</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="ksm-muted">Nessun ordine.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
