@extends('layouts.panel')

@section('title', 'Pagamenti · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    @php($canManage = auth()->user()->can(\App\Support\Permissions::PAYMENTS_MANAGE))

    {{-- Titolo, numero e filtri su una riga sola: l'elenco parte subito sotto. --}}
    <div class="ksm-listhead">
        <h1>Pagamenti <span class="ksm-listhead__count">{{ number_format($payments->total(), 0, ',', '.') }}</span></h1>

        <form method="GET" class="ksm-listhead__filters">
            <select class="ksm-select" name="metodo" aria-label="Metodo" onchange="this.form.submit()">
                <option value="">Tutti i metodi</option>
                @foreach ($methods as $method)
                    <option value="{{ $method }}" @selected(request('metodo') === $method)>{{ $method }}</option>
                @endforeach
            </select>
            <select class="ksm-select" name="stato" aria-label="Stato" onchange="this.form.submit()">
                <option value="">Tutti gli stati</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('stato') === $status)>{{ $status }}</option>
                @endforeach
            </select>
            @if (request()->hasAny(['metodo', 'stato']))
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.payments.index') }}">Azzera</a>
            @endif
        </form>
    </div>

    @if ($canManage && $payments->isNotEmpty())
        @include('partials.bulk-bar', [
            'action' => route('admin.payments.bulk'),
            'paginator' => $payments,
            'noun' => 'movimenti',
            'nounOne' => 'movimento',
            'actions' => ['delete' => 'Elimina'],
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
                <th>Data</th>
                <th>Cliente</th>
                <th>Azienda</th>
                <th>Metodo</th>
                <th>Importo</th>
                <th>Stato</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($payments as $payment)
                <tr>
                    @if ($canManage)
                        <td class="ksm-bulk__cell">
                            <input type="checkbox" name="ids[]" value="{{ $payment->id }}" form="bulk" data-bulk-item
                                   aria-label="Seleziona il movimento del {{ $payment->created_at?->format('d/m/Y') }}">
                        </td>
                    @endif
                    <td style="white-space: nowrap;">{{ $payment->created_at?->format('d/m/Y') }}</td>
                    <td>{{ $payment->user?->name }}</td>
                    <td>{{ $payment->company?->name }}</td>
                    <td>{{ $payment->method }} <span class="ksm-muted">({{ $payment->mode }})</span></td>
                    <td style="white-space: nowrap;">{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</td>
                    <td><span class="ksm-badge ksm-badge--muted">{{ $payment->status }}</span></td>
                    <td style="text-align: right;">
                        @if ($canManage)
                            <form method="POST" action="{{ route('admin.payments.destroy', $payment) }}"
                                  onsubmit="return confirm('Eliminare il movimento?');">
                                @csrf @method('DELETE')
                                <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Elimina</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $canManage ? 8 : 7 }}" class="ksm-muted">Nessun movimento.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $payments->links() }}</div>

    <section class="ksm-box" style="margin-top: 28px;">
        <div class="ksm-box__head"><h2>Ultimi abbonamenti ai piani</h2></div>
        <div class="ksm-table-wrap">
            <table class="ksm-table">
                <thead><tr><th>Azienda</th><th>Piano</th><th>Importo</th><th>Stato</th></tr></thead>
                <tbody>
                @forelse ($subscriptions as $subscription)
                    <tr>
                        <td>{{ $subscription->company?->name }}</td>
                        <td>{{ $subscription->plan?->name }}</td>
                        <td>{{ \App\Support\Money::format($subscription->amount, $subscription->currency) }}</td>
                        <td>{{ $subscription->status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="ksm-muted">Nessun abbonamento.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
