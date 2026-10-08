@use('App\Support\Analytics\Format')
@use('App\Support\Analytics\Period')

@php
    $keep = array_filter(['sito' => $host] + ($period->key === 'personalizzato'
        ? ['dal' => $period->from->toDateString(), 'al' => $period->to->toDateString()]
        : ['periodo' => $period->key]));
    $pageLink = fn ($target) => route('admin.analytics.page', $keep + ['path' => $target]);
    $max = max(1, ...array_column($page['series'], 'views'));
@endphp

@extends('layouts.panel')

@section('title', 'Dettaglio pagina · KSM')
@section('role', 'Amministrazione')
@section('crumb', 'Statistiche visite')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <div>
            <h1>Dettaglio pagina</h1>
            <p class="ksm-muted" style="margin: 4px 0 0;"><strong>{{ $path }}</strong> · {{ $host ?? 'tutti i siti' }} · {{ $period->label() }}</p>
        </div>
        <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.analytics.index', $keep) }}">← Torna alle statistiche</a>
    </div>

    @if ($page['views'] === 0)
        <section class="ksm-box"><p class="ksm-muted" style="margin: 0;">Nessuna visualizzazione di questa pagina nel periodo.</p></section>
    @else
        <div class="ksm-kpis" style="--kpi-cols: 5; --kpi-wide: 5;">
            @foreach ([
                ['Pagine viste', Format::number($page['views'])],
                ['Visitatori', Format::number($page['visitors'])],
                ['Visite iniziate qui', Format::number($page['entries'])],
                ['Uscite', Format::number($page['exits']).' · '.Format::percent($page['exits'] / $page['views'])],
                ['Tempo medio', $page['avg_time'] > 0 ? Format::duration($page['avg_time']) : '—'],
            ] as [$label, $value])
                <div class="ksm-kpi ksm-kpi--dark ksm-kpi--static">
                    <span class="ksm-kpi__value">{{ $value }}</span>
                    <span class="ksm-kpi__label">{{ $label }}</span>
                    @if ($label === 'Visite iniziate qui' && $page['entries'] > 0)
                        <small class="ksm-muted">rimbalzo {{ Format::percent($page['bounces'] / $page['entries']) }}</small>
                    @endif
                </div>
            @endforeach
        </div>

        <section class="ksm-box">
            <div class="ksm-box__head"><h2>Visualizzazioni giorno per giorno</h2></div>
            <div class="ksm-spark" role="img" aria-label="Visualizzazioni della pagina per giorno">
                @foreach ($page['series'] as $point)
                    <i style="height: {{ max(2, round($point['views'] / $max * 100)) }}%" title="{{ $point['label'] }}: {{ Format::number($point['views']) }}"></i>
                @endforeach
            </div>
        </section>

        <div class="ksm-panel-grid ksm-panel-grid--even">
            @foreach ([['Da dove si arriva a questa pagina', $page['previous'], 'Ingresso diretto o fuori sito: vedi le fonti qui sotto.'], ['Dove si va dopo', $page['next'], 'Chi esce dal sito dopo questa pagina non compare.']] as [$title, $rows, $hint])
                <section class="ksm-box">
                    <div class="ksm-box__head"><h2>{{ $title }}</h2></div>
                    <p class="ksm-box__hint">{{ $hint }}</p>
                    @if ($rows->isEmpty())
                        <p class="ksm-muted" style="margin: 0;">Nessun passaggio.</p>
                    @else
                        <div class="ksm-table-wrap ksm-table-wrap--flush">
                            <table class="ksm-table ksm-table--stats" data-flat>
                                <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td><a class="ksm-path" href="{{ $pageLink($row->path) }}" title="{{ $row->path }}">{{ $row->path }}</a></td>
                                        <td class="is-num">{{ Format::number($row->count) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

        @if ($page['sources']->isNotEmpty())
            <section class="ksm-box">
                <div class="ksm-box__head"><h2>Fonti delle visite che cominciano qui</h2></div>
                <ul class="ksm-meterlist">
                    @php $topSource = max(1, $page['sources']->first()->count); @endphp
                    @foreach ($page['sources'] as $row)
                        <li>
                            <span class="ksm-meterlist__top"><span>{{ $row->label }} <small class="ksm-muted">{{ $row->channel_label }}</small></span><span class="ksm-muted">{{ Format::number($row->count) }}</span></span>
                            <span class="ksm-meter"><i style="width: {{ round($row->count / $topSource * 100) }}%"></i></span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
@endsection
