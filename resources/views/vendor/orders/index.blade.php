@extends('layouts.panel')

@section('title', 'Ordini · KSM')
@section('role', 'Area azienda')
@section('nav')@include('vendor.nav')@endsection

@section('content')
    {{-- Titolo, numero e filtro su una riga sola: l'elenco parte subito sotto. --}}
    <div class="ksm-listhead">
        <h1>Ordini <span class="ksm-listhead__count">{{ number_format($orders->total(), 0, ',', '.') }}</span></h1>

        <form method="GET" class="ksm-listhead__filters">
            <select class="ksm-select" name="stato" aria-label="Stato" onchange="this.form.submit()">
                <option value="">Tutti gli stati</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('stato') === $status)>{{ \App\Models\Order::STATUS_LABELS[$status] ?? $status }}</option>
                @endforeach
            </select>
            @if (request()->filled('stato'))
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('vendor.orders.index') }}">Azzera</a>
            @endif
        </form>
    </div>

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead><tr><th>Ordine</th><th>Data</th><th>Cliente</th><th>Totale</th><th>Stato</th><th></th></tr></thead>
            <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td><a href="{{ route('vendor.orders.show', $order) }}" style="font-weight: 600;">{{ $order->reference }}</a></td>
                    <td style="white-space: nowrap;">{{ $order->created_at?->format('d/m/Y') }}</td>
                    <td>{{ $order->billing_name }}</td>
                    <td style="white-space: nowrap;">{{ \App\Support\Money::format($order->total) }}</td>
                    <td><span class="ksm-badge ksm-badge--{{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                    <td style="text-align: right;">
                        <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('vendor.orders.show', $order) }}">Apri</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="ksm-muted">Nessun ordine.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $orders->links() }}</div>
@endsection
