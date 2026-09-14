@extends('layouts.panel')

@section('title', 'Pagamenti · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head"><h1>Pagamenti</h1></div>

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead><tr><th>Data</th><th>Cliente</th><th>Azienda</th><th>Metodo</th><th>Importo</th><th>Stato</th><th></th></tr></thead>
            <tbody>
            @forelse ($payments as $payment)
                <tr>
                    <td>{{ $payment->created_at?->format('d/m/Y') }}</td>
                    <td>{{ $payment->user?->name }}</td>
                    <td>{{ $payment->company?->name }}</td>
                    <td>{{ $payment->method }} <span class="ksm-muted">({{ $payment->mode }})</span></td>
                    <td>{{ \App\Support\Money::format($payment->amount, $payment->currency) }}</td>
                    <td><span class="ksm-badge ksm-badge--muted">{{ $payment->status }}</span></td>
                    <td style="text-align: right;">
                        <form method="POST" action="{{ route('admin.payments.destroy', $payment) }}"
                              onsubmit="return confirm('Eliminare il movimento?');">
                            @csrf @method('DELETE')
                            <button class="ksm-btn ksm-btn--ghost" type="submit">Elimina</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="ksm-muted">Nessun movimento.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $payments->links() }}</div>

    <h2 style="font-size: 1.1rem; margin-top: 34px;">Abbonamenti ai piani</h2>
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
@endsection
