@use('App\Support\Analytics\Format')
{{-- Obiettivi: cosa fanno i visitatori oltre a guardare --}}
<section class="ksm-box" id="obiettivi">
    <div class="ksm-box__head"><h2>Obiettivi raggiunti</h2></div>
    <p class="ksm-box__hint">Registrazioni, prodotti messi nel carrello, ordini avviati e messaggi inviati dal modulo contatti. La quota è sulle visite del periodo.</p>

    <div class="ksm-kpis" style="--kpi-cols: 4; --kpi-wide: 4;">
        @foreach ($goals as $goal)
            @php
                $change = $goal['change'];
                $state = match (true) { $change === null => 'none', abs($change) < 0.005 => 'flat', $change > 0 => 'up', default => 'down' };
            @endphp
            <div class="ksm-kpi ksm-kpi--dark ksm-kpi--static">
                <span class="ksm-kpi__value">{{ Format::number($goal['count']) }}</span>
                <span class="ksm-kpi__label">{{ $goal['label'] }}</span>
                <span class="ksm-delta ksm-delta--{{ $state }}">
                    @if ($state === 'none') nessun confronto
                    @elseif ($state === 'flat') invariato
                    @else {{ $change > 0 ? '▲' : '▼' }} {{ Format::number(round(abs($change) * 100)) }}%
                    @endif
                </span>
                <small class="ksm-muted">
                    {{ Format::percent($goal['rate']) }} delle visite
                    @if ($goal['goal'] === 'order' && $goal['value'] > 0)
                        · € {{ number_format($goal['value'], 2, ',', '.') }}
                    @endif
                </small>
            </div>
        @endforeach
    </div>
</section>

@if (! $empty)
    <div class="ksm-panel-grid ksm-panel-grid--even">
        <section class="ksm-box">
            <div class="ksm-box__head"><h2>Dalla visita all'ordine</h2></div>
            <p class="ksm-box__hint">Quante visite arrivano a ogni passo. Chi arriva dritto alla cassa salta i primi.</p>

            <ul class="ksm-meterlist">
                @foreach ($funnel as $i => $step)
                    <li>
                        <span class="ksm-meterlist__top">
                            <span>{{ $step['label'] }}</span>
                            <span class="ksm-muted">
                                {{ Format::number($step['count']) }} · {{ Format::percent($step['share']) }}
                                @if ($i > 0 && $funnel[$i - 1]['count'] > 0)
                                    <small>({{ Format::percent($step['step']) }} del passo prima)</small>
                                @endif
                            </span>
                        </span>
                        <span class="ksm-meter"><i style="width: {{ round($step['share'] * 100) }}%"></i></span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="ksm-box">
            <div class="ksm-box__head"><h2>Quali fonti portano risultati</h2></div>

            @if ($conversionSources->isEmpty())
                <p class="ksm-muted" style="margin: 0;">Nessun obiettivo raggiunto in questo periodo.</p>
            @else
                <div class="ksm-table-wrap ksm-table-wrap--flush">
                    <table class="ksm-table ksm-table--stats" data-flat>
                        <thead><tr><th>Fonte</th><th class="is-num">Visite</th><th class="is-num">Ordini</th><th class="is-num">Iscritti</th><th class="is-num">Messaggi</th><th class="is-num">Resa</th></tr></thead>
                        <tbody>
                        @foreach ($conversionSources as $row)
                            <tr>
                                <td><span class="ksm-path" title="{{ $row->source }}">{{ $row->source }}</span> <small class="ksm-muted">{{ $row->channel_label }}</small></td>
                                <td class="is-num">{{ Format::number($row->sessions) }}</td>
                                <td class="is-num">{{ Format::number($row->orders) }}@if ($row->revenue > 0)<small class="ksm-muted"> € {{ number_format($row->revenue, 0, ',', '.') }}</small>@endif</td>
                                <td class="is-num">{{ Format::number($row->signups) }}</td>
                                <td class="is-num">{{ Format::number($row->contacts) }}</td>
                                <td class="is-num ksm-muted">{{ Format::percent($row->rate) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endif
