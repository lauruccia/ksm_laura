@use('App\Support\Analytics\Format')
@use('App\Support\Analytics\Period')

@php
    $keep = array_filter(['sito' => $host]);
    $link = fn (array $extra = []) => route('admin.analytics.index', $keep + $extra);
    $exportQuery = array_filter(['sito' => $host] + ($period->key === 'personalizzato'
        ? ['dal' => $period->from->toDateString(), 'al' => $period->to->toDateString()]
        : ['periodo' => $period->key]));

    $icons = [
        'visitors' => 'users', 'sessions' => 'globe', 'views' => 'file',
        'pages_per_session' => 'list', 'avg_duration' => 'clock', 'bounce_rate' => 'logout',
    ];
    $tones = [
        'visitors' => 'accent', 'sessions' => 'dark', 'views' => 'dark',
        'pages_per_session' => 'dark', 'avg_duration' => 'money', 'bounce_rate' => 'muted',
    ];
    $pageLink = fn ($target) => route('admin.analytics.page', $exportQuery + ['path' => $target]);
    $days = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
@endphp

@extends('layouts.panel')

@section('title', 'Statistiche visite · KSM')
@section('role', 'Amministrazione')
@section('crumb', 'Statistiche visite')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <div>
            <h1>Statistiche visite</h1>
            <p class="ksm-muted" style="margin: 4px 0 0;">
                {{ $host ?? 'Tutti i siti della rete' }} · {{ $period->label() }}
            </p>
        </div>

        <span class="ksm-live" title="Persone con una pagina vista negli ultimi cinque minuti">
            <i class="ksm-live__dot" aria-hidden="true"></i>
            {{ Format::number($live) }} {{ $live === 1 ? 'persona online' : 'persone online' }}
        </span>
    </div>

    {{-- Periodo, sito ed esportazione ------------------------------------------------ --}}
    <div class="ksm-stats-bar">
        <nav class="ksm-seg" aria-label="Periodo">
            @foreach (Period::PRESETS as $key => $label)
                <a href="{{ $link(['periodo' => $key]) }}" @if ($period->key === (string) $key) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <form method="GET" class="ksm-stats-filters" role="search">
            @if ($period->key !== 'personalizzato')
                <input type="hidden" name="periodo" value="{{ $period->key }}">
            @endif

            <select class="ksm-select" name="sito" aria-label="Sito" onchange="this.form.submit()">
                <option value="">Tutti i siti</option>
                @foreach ($hosts as $option)
                    <option value="{{ $option }}" @selected($host === $option)>{{ $option }}</option>
                @endforeach
            </select>

            <label class="ksm-muted ksm-stats-date">dal
                <input class="ksm-input" type="date" name="dal" value="{{ $period->key === 'personalizzato' ? $period->from->toDateString() : '' }}"
                       max="{{ Period::today()->toDateString() }}" aria-label="Dal giorno">
            </label>
            <label class="ksm-muted ksm-stats-date">al
                <input class="ksm-input" type="date" name="al" value="{{ $period->key === 'personalizzato' ? $period->to->toDateString() : '' }}"
                       max="{{ Period::today()->toDateString() }}" aria-label="Al giorno">
            </label>
            <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Applica</button>
        </form>

        <details class="ksm-menu">
            <summary class="ksm-btn ksm-btn--ghost ksm-btn--sm">Esporta CSV <x-icon name="chevron-down" :size="14" /></summary>
            <div class="ksm-menu__list">
                <a href="{{ route('admin.analytics.export', ['type' => 'giorni'] + $exportQuery) }}">Andamento per giorno</a>
                <a href="{{ route('admin.analytics.export', ['type' => 'pagine'] + $exportQuery) }}">Pagine</a>
                <a href="{{ route('admin.analytics.export', ['type' => 'sorgenti'] + $exportQuery) }}">Provenienza</a>
            </div>
        </details>
    </div>

    {{-- Schede ------------------------------------------------------------------------ --}}
    <div class="ksm-kpis" style="--kpi-cols: 6; --kpi-wide: 6;">
        @foreach ($summary as $card)
            @php
                $change = $card['change'];
                $good = $change === null ? null : ($card['inverse'] ? $change < 0 : $change > 0);
                $state = match (true) {
                    $change === null => 'none',
                    abs($change) < 0.005 => 'flat',
                    $good => 'up',
                    default => 'down',
                };
            @endphp
            <div class="ksm-kpi ksm-kpi--{{ $tones[$card['key']] }} ksm-kpi--static">
                <span class="ksm-kpi__icon"><x-icon :name="$icons[$card['key']]" :size="20" /></span>
                <span class="ksm-kpi__value">{{ $card['value'] }}</span>
                <span class="ksm-kpi__label">{{ $card['label'] }}</span>
                <span class="ksm-delta ksm-delta--{{ $state }}">
                    @if ($state === 'none')
                        nessun confronto
                    @elseif ($state === 'flat')
                        invariato
                    @else
                        {{ $change > 0 ? '▲' : '▼' }} {{ Format::number(round(abs($change) * 100)) }}%
                    @endif
                    <small>{{ $state === 'none' ? '' : 'sul periodo prima' }}</small>
                </span>
            </div>
        @endforeach
    </div>

    @if ($empty)
        <section class="ksm-box">
            <div class="ksm-chart ksm-chart--empty">
                <x-icon name="chart" :size="28" />
                <p>Nessuna visita in questo periodo.</p>
                <span class="ksm-muted">
                    Le visite si contano dal momento della pubblicazione di questa funzione. Non entrano nei conti
                    i programmi automatici, chi amministra il sito dopo aver fatto accesso e chi ha attivato "Do Not Track".
                </span>
            </div>
        </section>
    @else
        <nav class="ksm-subnav" aria-label="Sezioni">
            <a href="#andamento">Andamento</a><a href="#pagine">Pagine</a><a href="#provenienza">Provenienza</a>
            <a href="#obiettivi">Obiettivi</a><a href="#aziende">Aziende e prodotti</a><a href="#pubblico">Pubblico</a>
            <a href="#ritorno">Nuovi e di ritorno</a><a href="#regioni">Regioni e città</a><a href="#quando">Quando</a>
        </nav>

        {{-- Andamento ------------------------------------------------------------------- --}}
        <section class="ksm-box" id="andamento">
            <div class="ksm-box__head">
                <h2>Andamento {{ ['hour' => 'ora per ora', 'day' => 'giorno per giorno', 'month' => 'mese per mese'][$granularity] }}</h2>
                <span class="ksm-legend">
                    <i class="ksm-legend__key ksm-legend__key--bar"></i> pagine viste
                    <i class="ksm-legend__key ksm-legend__key--line"></i> visitatori
                </span>
            </div>

            @include('admin.analytics.trend', ['series' => $series])
        </section>

        {{-- Pagine ---------------------------------------------------------------------- --}}
        <div class="ksm-panel-grid ksm-panel-grid--even" id="pagine">
            <section class="ksm-box">
                <div class="ksm-box__head">
                    <h2>Pagine più viste</h2>
                    <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.analytics.export', ['type' => 'pagine'] + $exportQuery) }}">Tutte (CSV)</a>
                </div>

                <div class="ksm-table-wrap ksm-table-wrap--flush">
                    <table class="ksm-table ksm-table--stats" data-flat>
                        <thead>
                        <tr><th>Pagina</th><th class="is-num">Viste</th><th class="is-num">Visitatori</th><th class="is-num" title="Media di chi ha comunicato una durata">Tempo medio</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($pages as $page)
                            <tr>
                                <td>
                                    <a class="ksm-path" href="{{ $pageLink($page->path) }}" title="Dettaglio: {{ $page->path }}">{{ $page->path }}</a>
                                    <span class="ksm-meter ksm-meter--thin"><i style="width: {{ round($page->share * 100) }}%"></i></span>
                                </td>
                                <td class="is-num">{{ Format::number($page->views) }}</td>
                                <td class="is-num">{{ Format::number($page->visitors) }}</td>
                                <td class="is-num ksm-muted">{{ $page->avg_time > 0 ? Format::duration($page->avg_time) : '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="ksm-box">
                <div class="ksm-box__head"><h2>Pagine di ingresso</h2></div>
                <p class="ksm-box__hint">Da dove comincia la visita, e quante visite si fermano lì (rimbalzo).</p>

                <div class="ksm-table-wrap ksm-table-wrap--flush">
                    <table class="ksm-table ksm-table--stats" data-flat>
                        <thead>
                        <tr><th>Pagina</th><th class="is-num">Visite</th><th class="is-num">Rimbalzo</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($entryPages as $page)
                            <tr>
                                <td><span class="ksm-path" title="{{ $page->path }}">{{ $page->path }}</span></td>
                                <td class="is-num">{{ Format::number($page->entries) }}</td>
                                <td class="is-num ksm-muted">{{ Format::percent($page->bounce_rate) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        {{-- Provenienza ---------------------------------------------------------------- --}}
        <div class="ksm-panel-grid ksm-panel-grid--even" id="provenienza">
            <section class="ksm-box">
                <div class="ksm-box__head"><h2>Da dove arrivano</h2></div>

                <ul class="ksm-meterlist">
                    @foreach ($channels as $channel)
                        <li>
                            <span class="ksm-meterlist__top">
                                <span>{{ $channel->label }}</span>
                                <span class="ksm-muted">{{ Format::number($channel->count) }} · {{ Format::percent($channel->share) }}</span>
                            </span>
                            <span class="ksm-meter"><i style="width: {{ round($channel->share * 100) }}%"></i></span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="ksm-box">
                <div class="ksm-box__head">
                    <h2>Fonti di traffico</h2>
                    <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.analytics.export', ['type' => 'sorgenti'] + $exportQuery) }}">CSV</a>
                </div>

                <div class="ksm-table-wrap ksm-table-wrap--flush">
                    <table class="ksm-table ksm-table--stats" data-flat>
                        <thead><tr><th>Fonte</th><th>Canale</th><th class="is-num">Visite</th></tr></thead>
                        <tbody>
                        @foreach ($sources as $source)
                            <tr>
                                <td><span class="ksm-path" title="{{ $source->source }}">{{ $source->source }}</span></td>
                                <td class="ksm-muted">{{ $source->channel_label }}</td>
                                <td class="is-num">{{ Format::number($source->count) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        @if ($campaigns->isNotEmpty())
            <section class="ksm-box">
                <div class="ksm-box__head"><h2>Campagne</h2></div>
                <p class="ksm-box__hint">Gli indirizzi con parametri <code>utm_campaign</code>, <code>utm_source</code> e <code>utm_medium</code>.</p>

                <div class="ksm-table-wrap ksm-table-wrap--flush">
                    <table class="ksm-table ksm-table--stats" data-flat>
                        <thead><tr><th>Campagna</th><th>Fonte</th><th>Mezzo</th><th class="is-num">Visite</th></tr></thead>
                        <tbody>
                        @foreach ($campaigns as $campaign)
                            <tr>
                                <td>{{ $campaign->campaign }}</td>
                                <td class="ksm-muted">{{ $campaign->source ?? '—' }}</td>
                                <td class="ksm-muted">{{ $campaign->medium ?? '—' }}</td>
                                <td class="is-num">{{ Format::number($campaign->count) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @include('admin.analytics._goals')
        @include('admin.analytics._catalogue')

        {{-- Pubblico ------------------------------------------------------------------- --}}
        <div class="ksm-panel-grid ksm-panel-grid--even" id="pubblico">
            <section class="ksm-box">
                <div class="ksm-box__head"><h2>Paesi</h2></div>

                <ul class="ksm-meterlist">
                    @foreach ($countries as $country)
                        <li>
                            <span class="ksm-meterlist__top">
                                <span @class(['ksm-muted' => $country->key === null])>{{ $country->label }}</span>
                                <span class="ksm-muted">{{ Format::number($country->count) }} · {{ Format::percent($country->share) }}</span>
                            </span>
                            <span class="ksm-meter"><i style="width: {{ round($country->share * 100) }}%"></i></span>
                        </li>
                    @endforeach
                </ul>

                @if ($countries->count() === 1 && $countries->first()->key === null)
                    <p class="ksm-box__hint" style="margin: 14px 0 0;">
                        Il paese non viene rilevato: serve un servizio davanti al sito che lo indichi
                        (per esempio Cloudflare) oppure l'archivio geografico .mmdb (vedi sotto, «Regioni italiane»).
                    </p>
                @endif
            </section>

            <section class="ksm-box">
                <div class="ksm-box__head"><h2>Dispositivi, browser e sistemi</h2></div>

                <div class="ksm-stats-trio">
                    @foreach ([['Dispositivo', $devices], ['Browser', $browsers], ['Sistema', $systems]] as [$title, $rows])
                        <div>
                            <h3 class="ksm-box__subhead">{{ $title }}</h3>
                            <ul class="ksm-meterlist">
                                @foreach ($rows as $row)
                                    <li>
                                        <span class="ksm-meterlist__top">
                                            <span>{{ $row->label }}</span>
                                            <span class="ksm-muted">{{ Format::percent($row->share) }}</span>
                                        </span>
                                        <span class="ksm-meter"><i style="width: {{ round($row->share * 100) }}%"></i></span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        @include('admin.analytics._audience')

        {{-- Quando --------------------------------------------------------------------- --}}
        <section class="ksm-box" id="quando">
            <div class="ksm-box__head">
                <h2>Quando arrivano</h2>
                <span class="ksm-muted" style="font-size: .82rem;">pagine viste per giorno della settimana e ora (ora italiana)</span>
            </div>

            <div class="ksm-heat" role="img" aria-label="Pagine viste per giorno della settimana e ora del giorno">
                <span></span>
                @foreach (range(0, 23) as $hour)
                    <span class="ksm-heat__hour">{{ $hour % 3 === 0 ? sprintf('%02d', $hour) : '' }}</span>
                @endforeach

                @foreach ($heatmap['cells'] as $weekday => $hours)
                    <span class="ksm-heat__day">{{ $days[$weekday] }}</span>
                    @foreach ($hours as $hour => $total)
                        <i class="ksm-heat__cell" style="--v: {{ round($total / $heatmap['max'], 3) }}"
                           title="{{ $days[$weekday] }} {{ sprintf('%02d', $hour) }}:00 · {{ Format::number($total) }} pagine viste"></i>
                    @endforeach
                @endforeach
            </div>
        </section>

        {{-- Siti ----------------------------------------------------------------------- --}}
        @if ($sites->isNotEmpty())
            <section class="ksm-box">
                <div class="ksm-box__head"><h2>Siti più visitati</h2></div>

                <div class="ksm-table-wrap ksm-table-wrap--flush">
                    <table class="ksm-table ksm-table--stats" data-flat>
                        <thead>
                        <tr><th>Sito</th><th class="is-num">Pagine viste</th><th class="is-num">Visitatori</th><th class="is-num">Visite</th><th></th></tr>
                        </thead>
                        <tbody>
                        @foreach ($sites as $site)
                            <tr>
                                <td>{{ $site->host }}</td>
                                <td class="is-num">{{ Format::number($site->views) }}</td>
                                <td class="is-num">{{ Format::number($site->visitors) }}</td>
                                <td class="is-num">{{ Format::number($site->sessions) }}</td>
                                <td class="is-num"><a href="{{ route('admin.analytics.index', ($period->key === 'personalizzato' ? ['dal' => $period->from->toDateString(), 'al' => $period->to->toDateString()] : ['periodo' => $period->key]) + ['sito' => $site->host]) }}">Apri</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    @endif

    {{-- Come si contano ---------------------------------------------------------------- --}}
    <details class="ksm-box ksm-howto">
        <summary>Come si contano questi numeri</summary>
        <ul>
            <li><strong>Di base senza cookie.</strong> L'indirizzo IP non si salva: serve solo a calcolare un'impronta che cambia ogni notte (e, con l'archivio geografico, regione e città). Non si può risalire a una persona, né riconoscerla da un giorno all'altro. Solo se si accende <code>KSM_ANALYTICS_RETURNING</code> un cookie anonimo permette di distinguere nuovi e di ritorno.</li>
            <li><strong>Obiettivi</strong> si contano nel momento dell'azione (iscrizione, carrello, ordine avviato, messaggio inviato) e si legano alla visita in corso per sapere da quale fonte arrivava. Un «ordine» è creato alla cassa, non necessariamente già pagato.</li>
            <li><strong>Visitatori</strong> sono persone distinte per giorno: chi torna dopo qualche giorno conta di nuovo.</li>
            <li><strong>Visite</strong> sono gruppi di pagine della stessa persona senza pause oltre i 30 minuti. <strong>Rimbalzo</strong> è la visita che si ferma alla prima pagina.</li>
            <li><strong>Durata</strong> è il tempo in cui la pagina è rimasta in primo piano, comunicato dal browser quando la si lascia. Se il browser non lo comunica, conta zero: la durata vera è un po' più alta.</li>
            <li><strong>Non si contano</strong> i programmi automatici, chi amministra il sito dopo aver fatto accesso, chi ha attivo «Do Not Track» e le aree riservate (account, pagamenti, amministrazione).</li>
            <li>Le visite si conservano {{ config('ksm.analytics.retention_days') }} giorni, poi si cancellano da sole.</li>
        </ul>
    </details>
@endsection
