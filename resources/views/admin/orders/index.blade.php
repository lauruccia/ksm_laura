@extends('layouts.panel')

@section('title', 'Ordini · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    @php($canManage = auth()->user()->can(\App\Support\Permissions::ORDERS_MANAGE))

    {{-- Titolo, numero e filtri su una riga sola: l'elenco parte subito sotto. --}}
    <div class="ksm-listhead">
        <h1>Ordini <span class="ksm-listhead__count">{{ number_format($orders->total(), 0, ',', '.') }}</span></h1>

        <form method="GET" class="ksm-listhead__filters" role="search">
            <span class="ksm-listhead__search">
                <x-icon name="search" :size="16" />
                <input class="ksm-input" type="search" name="cerca" value="{{ request('cerca') }}"
                       placeholder="Numero, cliente o email" aria-label="Numero, cliente o email">
            </span>
            <select class="ksm-select" name="stato" aria-label="Stato" onchange="this.form.submit()">
                <option value="">Tutti gli stati</option>
                @foreach (\App\Models\Order::STATUS_LABELS as $status => $label)
                    <option value="{{ $status }}" @selected(request('stato') === $status)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Cerca</button>
            @if (request()->hasAny(['cerca', 'stato']))
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.orders.index') }}">Azzera</a>
            @endif
        </form>
    </div>

    @if ($canManage && $orders->isNotEmpty())
        @include('partials.bulk-bar', [
            'action' => route('admin.orders.bulk'),
            'paginator' => $orders,
            'noun' => 'ordini',
            'nounOne' => 'ordine',
            'actions' => ['status' => 'Cambia stato in', 'delete' => 'Elimina'],
            'statuses' => \App\Models\Order::STATUS_LABELS,
            'onlySelected' => ['delete'],
        ])
    @endif

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead>
            <tr>
                @if ($canManage)
                    <th class="ksm-bulk__cell"><input type="checkbox" data-bulk-page aria-label="Seleziona tutti in questa pagina"></th>
                @endif
                <th>Ordine</th>
                <th>Data</th>
                <th>Cliente</th>
                <th>Azienda</th>
                <th>Totale</th>
                <th>Stato</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($orders as $order)
                <tr>
                    @if ($canManage)
                        <td class="ksm-bulk__cell">
                            <input type="checkbox" name="ids[]" value="{{ $order->id }}" form="bulk" data-bulk-item
                                   aria-label="Seleziona l'ordine {{ $order->reference }}">
                        </td>
                    @endif
                    <td><a href="{{ route('admin.orders.show', $order) }}" style="font-weight: 600;">{{ $order->reference }}</a></td>
                    <td style="white-space: nowrap;">{{ $order->created_at?->format('d/m/Y') }}</td>
                    <td>
                        {{ $order->billing_name }}
                        @if ($order->billing_email)
                            <small class="ksm-muted" style="display: block;">{{ $order->billing_email }}</small>
                        @endif
                    </td>
                    <td>{{ $order->company?->name }}</td>
                    <td style="white-space: nowrap;">{{ \App\Support\Money::format($order->total) }}</td>
                    <td><span class="ksm-badge ksm-badge--{{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                    <td style="text-align: right;">
                        <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.orders.show', $order) }}">Apri</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $canManage ? 8 : 7 }}" class="ksm-muted">Nessun ordine.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $orders->links() }}</div>
@endsection
