@extends('layouts.panel')

@section('title', 'KMoney · KSM')
@section('role', 'Area azienda')
@section('nav')@include('vendor.nav')@endsection

@section('content')
    @php($inDebt = (bool) $settings?->kmoney_in_debt)

    <div class="ksm-panel__head"><h1>KMoney</h1></div>

    <section class="ksm-box" style="margin-bottom: 22px; max-width: 780px;">
        <div class="ksm-box__head"><h2>Il tuo contratto</h2></div>

        <ul class="ksm-meta">
            <li>
                Quota del contratto:
                {{ $settings?->kmoney_contract_percent !== null ? $settings->kmoney_contract_percent.'%' : 'non ancora impostata' }}
            </li>
            <li>
                Conto collegato: {{ $settings?->kmoneyReady() ? 'sì' : 'no' }}
                · <a href="{{ route('vendor.payments.edit') }}">Incassi</a>
            </li>
        </ul>

        @if ($inDebt)
            <p class="ksm-alert ksm-alert--error">
                Il conto KMoney è in debito: tutti i prodotti si pagano al 100% in KMoney, e le quote
                non si possono cambiare finché il conto non torna in positivo.
            </p>
        @endif

        <small class="ksm-muted">
            Chi decide la quota di un prodotto, dal più forte: la scelta sul prodotto, poi quella della sua
            categoria, poi il contratto. I prodotti si selezionano insieme dall'elenco dei prodotti.
        </small>
    </section>

    <form class="ksm-box" style="max-width: 780px;" method="POST" action="{{ route('vendor.kmoney.update') }}">
        @csrf @method('PUT')
        <div class="ksm-box__head"><h2>Quote per categoria</h2></div>

        @if ($categories->isEmpty())
            <p class="ksm-muted">Nessun prodotto con una categoria.</p>
        @else
            <div class="ksm-table-wrap">
                <table class="ksm-table">
                    <thead><tr><th>Categoria</th><th>Quota in KMoney</th></tr></thead>
                    <tbody>
                    @foreach ($categories as $category)
                        <tr>
                            <td>{{ $category->name }}</td>
                            <td>
                                <select class="ksm-select" name="rules[{{ $category->id }}]"
                                        aria-label="Quota KMoney per {{ $category->name }}" @disabled($inDebt)>
                                    <option value="">Come da contratto</option>
                                    @foreach ($steps as $step)
                                        <option value="{{ $step }}" @selected(($rules[$category->id] ?? null) === $step)>{{ $step }}%</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @unless ($inDebt)
                <button class="ksm-btn ksm-btn--primary" type="submit" style="margin-top: 14px;">Salva</button>
            @endunless
        @endif
    </form>
@endsection
