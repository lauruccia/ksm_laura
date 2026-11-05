@extends('layouts.panel')

@section('title', 'Importa prodotti · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <div>
            <h1>Importa prodotti da CSV</h1>
            @if ($company)
                <p class="ksm-panel__lead">Azienda: <strong>{{ $company->name }}</strong>
                    · <a href="{{ route('admin.products.import') }}">cambia</a></p>
            @endif
        </div>
        <div class="ksm-rowactions">
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.products.index', array_filter(['azienda' => $company?->id])) }}">Torna ai prodotti</a>
        </div>
    </div>

    @if ($errors->any())
        <p class="ksm-alert ksm-alert--error">{{ $errors->first() }}</p>
    @endif

    @if ($plan)
        <section class="ksm-box">
            <div class="ksm-box__head"><h2>Anteprima</h2></div>
            <p>
                <strong>{{ $plan['counts']['create'] }}</strong> nuovi ·
                <strong>{{ $plan['counts']['update'] }}</strong> da aggiornare ·
                <strong>{{ $plan['counts']['same'] }}</strong> già uguali ·
                <strong>{{ $plan['counts']['error'] }}</strong> con errori (non verranno importati).
                Non è stato ancora salvato nulla.
            </p>

            <div class="ksm-table-wrap">
                <table class="ksm-table">
                    <thead>
                        <tr><th>Riga</th><th>Prodotto</th><th>Esito</th><th>Prezzo</th><th>Quota KMoney</th><th>Cosa cambia / note</th></tr>
                    </thead>
                    <tbody>
                        @foreach (array_slice($plan['items'], 0, 300) as $it)
                            <tr>
                                <td>{{ $it['line'] }}</td>
                                <td>{{ $it['name'] }}@if ($it['sku'])<br><small class="ksm-muted">{{ $it['sku'] }}</small>@endif</td>
                                <td>{{ ['create' => 'Nuovo', 'update' => 'Aggiorna', 'same' => 'Già uguale', 'error' => 'Errore'][$it['action']] }}</td>
                                <td>@if (! empty($it['data']['price'])){{ number_format($it['data']['price'], 2, ',', '.') }} €@if ($it['variants']) <small>(da)</small>@endif @else — @endif</td>
                                <td>{{ isset($it['data']['kmoney_discount_percent']) ? $it['data']['kmoney_discount_percent'].'%' : 'automatica' }}</td>
                                <td>
                                    @foreach ($it['errors'] as $e)<div class="ksm-alert--error">{{ $e }}</div>@endforeach
                                    @foreach ($it['diff'] as $d)<div>{{ $d }}</div>@endforeach
                                    @foreach ($it['warnings'] as $w)<div class="ksm-muted">⚠ {{ $w }}</div>@endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($plan['items']) > 300)
                <small class="ksm-muted">Mostrate le prime 300 righe su {{ count($plan['items']) }}.</small>
            @endif

            @if ($plan['counts']['create'] + $plan['counts']['update'] > 0)
                <form method="POST" action="{{ route('admin.products.import.apply') }}" class="ksm-listhead__actions">
                    @csrf
                    <input type="hidden" name="azienda" value="{{ $company->id }}">
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="images" value="{{ $opts['images'] ? 1 : 0 }}">
                    <button class="ksm-btn ksm-btn--primary" type="submit">Conferma e importa</button>
                    <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.products.import', ['azienda' => $company->id]) }}">Annulla</a>
                </form>
            @else
                <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.products.import', ['azienda' => $company->id]) }}">Torna indietro</a>
            @endif
        </section>
    @elseif (! $company)
        <p class="ksm-panel__lead">I prodotti sono di un'azienda: cerca quella in cui importare il file.</p>
        <form method="GET" action="{{ route('admin.products.import') }}" class="ksm-listhead__filters" role="search" style="margin-bottom: 18px;">
            <span class="ksm-listhead__search">
                <x-icon name="search" :size="16" />
                <input class="ksm-input" type="search" name="cerca" value="{{ $term }}" placeholder="Nome dell'azienda" aria-label="Nome dell'azienda" autofocus>
            </span>
            <button class="ksm-btn ksm-btn--primary ksm-btn--sm" type="submit">Cerca</button>
        </form>

        @if ($term !== '')
            <div class="ksm-table-wrap">
                <table class="ksm-table">
                    <thead><tr><th>Azienda</th><th>Citta'</th><th>Stato</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($companies as $c)
                        <tr>
                            <td style="font-weight: 600;">{{ $c->name }}</td>
                            <td>{{ $c->city }}</td>
                            <td><span class="ksm-badge @unless ($c->is_active) ksm-badge--muted @endunless">{{ $c->is_active ? 'Attiva' : 'Spenta' }}</span></td>
                            <td style="text-align: right;">
                                <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route('admin.products.import', ['azienda' => $c->id]) }}">Importa qui</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="ksm-muted">Nessuna azienda con questo nome.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    @else
        <form class="ksm-box" method="POST" action="{{ route('admin.products.import.analyze') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="azienda" value="{{ $company->id }}">
            <div class="ksm-box__head"><h2>Carica il file</h2></div>
            <p>
                Un CSV nel formato di WooCommerce: quello esportato da kmoney (con la quota KMoney di ogni prodotto)
                oppure da WooCommerce (<em>Prodotti › Esporta</em>), anche in italiano. Prima vedi cosa cambierebbe, poi confermi.
                I prodotti si riconoscono dallo SKU: se c'è già si aggiorna, altrimenti si crea.
            </p>
            <p>
                <input class="ksm-input" type="file" name="csv" accept=".csv,text/csv,text/plain" required>
                <small class="ksm-muted">Massimo 5 MB e 1000 righe.</small>
            </p>
            <label>
                <input type="checkbox" name="images" value="1" checked>
                Scarica la prima immagine di ogni prodotto dai link del file
            </label>
            <p><button class="ksm-btn ksm-btn--primary" type="submit">Controlla il file</button></p>
        </form>
    @endif
@endsection
