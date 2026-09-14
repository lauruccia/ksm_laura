@extends('layouts.panel')

@section('title', 'Ordini · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>Ordini</h1>

        <form method="GET" style="display: flex; gap: 8px;">
            <select class="ksm-select" name="stato" onchange="this.form.submit()">
                <option value="">Tutti gli stati</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('stato') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead><tr><th>Ordine</th><th>Cliente</th><th>Azienda</th><th>Totale</th><th>Stato</th><th></th></tr></thead>
            <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td>{{ $order->reference }}</td>
                    <td>{{ $order->billing_name }}</td>
                    <td>{{ $order->company?->name }}</td>
                    <td>{{ \App\Support\Money::format($order->total) }}</td>
                    <td><span class="ksm-badge ksm-badge--muted">{{ $order->status }}</span></td>
                    <td style="text-align: right;">
                        <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.orders.show', $order) }}">Apri</a>
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
