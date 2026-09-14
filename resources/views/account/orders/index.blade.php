@extends('layouts.panel')

@section('title', 'I miei ordini · KSM')
@section('role', 'Il mio account')
@section('crumb', 'I miei ordini')
@section('nav')@include('account.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>I miei ordini</h1>
    </div>

    <form class="ksm-filters" method="GET">
        <select class="ksm-select" name="stato">
            <option value="">Tutti gli stati</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(request('stato') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button class="ksm-btn ksm-btn--ghost" type="submit">Filtra</button>
        @if (request('stato'))
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('account.orders.index') }}">Azzera</a>
        @endif
    </form>

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
                    <td><span class="ksm-badge ksm-badge--{{ $order->status }}">{{ $order->status }}</span></td>
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
