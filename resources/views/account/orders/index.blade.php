@extends('layouts.panel')

@section('title', 'I miei ordini · KSM')
@section('role', 'Il mio account')
@section('crumb', 'I miei ordini')
@section('nav')@include('account.nav')@endsection

@section('content')
    {{-- Titolo, numero e filtro su una riga sola: l'elenco parte subito sotto. --}}
    <div class="ksm-listhead">
        <h1>I miei ordini <span class="ksm-listhead__count">{{ number_format($orders->total(), 0, ',', '.') }}</span></h1>

        <form method="GET" class="ksm-listhead__filters">
            <select class="ksm-select" name="stato" aria-label="Stato" onchange="this.form.submit()">
                <option value="">Tutti gli stati</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('stato') === $status)>{{ \App\Models\Order::STATUS_LABELS[$status] ?? $status }}</option>
                @endforeach
            </select>
            @if (request('stato'))
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('account.orders.index') }}">Azzera</a>
            @endif
        </form>
    </div>

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead><tr><th>Ordine</th><th>Data</th><th>Azienda</th><th>Totale</th><th>Stato</th><th></th></tr></thead>
            <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td><a href="{{ route('account.orders.show', $order) }}">{{ $order->reference }}</a></td>
                    <td class="ksm-muted">{{ $order->created_at?->translatedFormat('j M Y') }}</td>
                    <td>{{ $order->company?->name }}</td>
                    <td>{{ \App\Support\Money::format($order->total) }}</td>
                    <td><span class="ksm-badge ksm-badge--{{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                    <td class="ksm-rowactions">
                        <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('account.orders.show', $order) }}">Apri</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="ksm-muted">Nessun ordine con questo filtro.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $orders->links() }}</div>
@endsection
