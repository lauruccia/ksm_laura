@use('App\Support\Analytics\Format')
{{-- Aziende e prodotti, ricerche, uscite --}}
<div class="ksm-panel-grid ksm-panel-grid--even" id="aziende">
    <section class="ksm-box">
        <div class="ksm-box__head"><h2>Aziende più guardate</h2></div>
        @if ($companies->isEmpty())
            <p class="ksm-muted" style="margin: 0;">Nessuna scheda azienda vista.</p>
        @else
            <div class="ksm-table-wrap ksm-table-wrap--flush">
                <table class="ksm-table ksm-table--stats" data-flat>
                    <thead><tr><th>Azienda</th><th class="is-num">Viste</th><th class="is-num">Visitatori</th><th class="is-num">Ingressi</th></tr></thead>
                    <tbody>
                    @foreach ($companies as $row)
                        <tr>
                            <td><a href="{{ $pageLink($row->path) }}" title="Dettaglio della pagina">{{ $row->name }}</a></td>
                            <td class="is-num">{{ Format::number($row->views) }}</td>
                            <td class="is-num">{{ Format::number($row->visitors) }}</td>
                            <td class="is-num ksm-muted">{{ Format::number($row->entries) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="ksm-box">
        <div class="ksm-box__head"><h2>Prodotti più guardati</h2></div>
        @if ($products->isEmpty())
            <p class="ksm-muted" style="margin: 0;">Nessun prodotto visto.</p>
        @else
            <div class="ksm-table-wrap ksm-table-wrap--flush">
                <table class="ksm-table ksm-table--stats" data-flat>
                    <thead><tr><th>Prodotto</th><th class="is-num">Viste</th><th class="is-num">Visitatori</th></tr></thead>
                    <tbody>
                    @foreach ($products as $row)
                        <tr>
                            <td><a href="{{ $pageLink($row->path) }}">{{ $row->name }}</a>@if ($row->company)<small class="ksm-muted"> · {{ $row->company }}</small>@endif</td>
                            <td class="is-num">{{ Format::number($row->views) }}</td>
                            <td class="is-num">{{ Format::number($row->visitors) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

@if ($storefronts->isNotEmpty())
    <section class="ksm-box">
        <div class="ksm-box__head"><h2>Siti delle aziende</h2></div>
        <p class="ksm-box__hint">Le aziende con un sito proprio: quante visite ricevono e quanti obiettivi (ordini, messaggi, carrelli) raggiungono.</p>
        <div class="ksm-table-wrap ksm-table-wrap--flush">
            <table class="ksm-table ksm-table--stats" data-flat>
                <thead><tr><th>Azienda</th><th>Sito</th><th class="is-num">Pagine viste</th><th class="is-num">Visitatori</th><th class="is-num">Visite</th><th class="is-num">Obiettivi</th></tr></thead>
                <tbody>
                @foreach ($storefronts as $row)
                    <tr>
                        <td>{{ $row->name }}</td>
                        <td class="ksm-muted">{{ $row->host }}</td>
                        <td class="is-num">{{ Format::number($row->views) }}</td>
                        <td class="is-num">{{ Format::number($row->visitors) }}</td>
                        <td class="is-num">{{ Format::number($row->sessions) }}</td>
                        <td class="is-num">{{ Format::number($row->goals) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

<div class="ksm-panel-grid ksm-panel-grid--even">
    <section class="ksm-box">
        <div class="ksm-box__head"><h2>Cosa si cerca nel sito</h2></div>
        @if ($searches->isEmpty())
            <p class="ksm-muted" style="margin: 0;">Nessuna ricerca nel periodo.</p>
        @else
            <ul class="ksm-meterlist">
                @php $top = max(1, $searches->first()->count); @endphp
                @foreach ($searches as $row)
                    <li>
                        <span class="ksm-meterlist__top"><span>{{ $row->term }}</span><span class="ksm-muted">{{ Format::number($row->count) }}</span></span>
                        <span class="ksm-meter"><i style="width: {{ round($row->count / $top * 100) }}%"></i></span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="ksm-box">
        <div class="ksm-box__head"><h2>Parole chiave dai motori e dalle campagne</h2></div>
        @if ($keywords->isEmpty())
            <p class="ksm-box__hint" style="margin: 0;">
                Nessuna parola in questo periodo. Google e quasi tutti i motori non comunicano più ai siti cosa ha scritto chi li trova:
                per quelle parole serve Google Search Console. Qui compaiono quelle che arrivano davvero (motori minori,
                parametro <code>utm_term</code> delle campagne).
            </p>
        @else
            <div class="ksm-table-wrap ksm-table-wrap--flush">
                <table class="ksm-table ksm-table--stats" data-flat>
                    <thead><tr><th>Parola</th><th>Fonte</th><th class="is-num">Visite</th></tr></thead>
                    <tbody>
                    @foreach ($keywords as $row)
                        <tr><td>{{ $row->term }}</td><td class="ksm-muted">{{ $row->source ?? '—' }}</td><td class="is-num">{{ Format::number($row->count) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

<section class="ksm-box">
    <div class="ksm-box__head"><h2>Pagine da cui si esce</h2></div>
    <p class="ksm-box__hint">L'ultima pagina di ogni visita e quanta parte delle sue visualizzazioni finisce lì.</p>
    <div class="ksm-table-wrap ksm-table-wrap--flush">
        <table class="ksm-table ksm-table--stats" data-flat>
            <thead><tr><th>Pagina</th><th class="is-num">Uscite</th><th class="is-num">Sulle viste</th></tr></thead>
            <tbody>
            @foreach ($exitPages as $row)
                <tr>
                    <td><a class="ksm-path" href="{{ $pageLink($row->path) }}" title="{{ $row->path }}">{{ $row->path }}</a></td>
                    <td class="is-num">{{ Format::number($row->exits) }}</td>
                    <td class="is-num ksm-muted">{{ Format::percent($row->rate) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
