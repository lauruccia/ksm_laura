@use('App\Support\Analytics\Format')
{{-- Nuovi e di ritorno, regioni e città --}}
<div class="ksm-panel-grid ksm-panel-grid--even">
    <section class="ksm-box" id="ritorno">
        <div class="ksm-box__head"><h2>Nuovi e di ritorno</h2></div>

        @if (! $returningEnabled && ($audience['new'] + $audience['returning']) === 0)
            <p class="ksm-box__hint" style="margin: 0;">
                Per riconoscere chi torna dopo giorni serve un cookie anonimo sul dispositivo. È spento:
                senza cookie non si può sapere chi è già stato qui. Per accenderlo imposta
                <code>KSM_ANALYTICS_RETURNING=true</code> nell'ambiente. Con il cookie attivo valuta se
                nell'informativa privacy e nel banner cookie serve citarlo (non è un parere legale).
            </p>
        @else
            @php $known = max(1, $audience['new'] + $audience['returning']); @endphp
            <ul class="ksm-meterlist">
                @foreach ([['Nuovi', $audience['new']], ['Di ritorno', $audience['returning']]] as [$label, $count])
                    <li>
                        <span class="ksm-meterlist__top">
                            <span>{{ $label }}</span>
                            <span class="ksm-muted">{{ Format::number($count) }} · {{ Format::percent($count / $known) }}</span>
                        </span>
                        <span class="ksm-meter"><i style="width: {{ round($count / $known * 100) }}%"></i></span>
                    </li>
                @endforeach
            </ul>
            @if ($audience['unknown'] > 0)
                <p class="ksm-box__hint" style="margin: 10px 0 0;">{{ Format::number($audience['unknown']) }} visite senza cookie (prima dell'attivazione, o chi lo rifiuta) non sono nel confronto.</p>
            @endif

            <div class="ksm-table-wrap ksm-table-wrap--flush" style="margin-top: 14px;">
                <table class="ksm-table ksm-table--stats" data-flat>
                    <thead><tr><th>Chi</th><th class="is-num">Visite</th><th class="is-num">Pagine per visita</th><th class="is-num">Durata media</th></tr></thead>
                    <tbody>
                    @foreach ($behaviour as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="is-num">{{ Format::number($row['sessions']) }}</td>
                            <td class="is-num">{{ Format::decimal($row['pages']) }}</td>
                            <td class="is-num">{{ Format::duration($row['duration']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="ksm-box">
        <div class="ksm-box__head"><h2>Chi torna dopo la prima visita</h2></div>
        <p class="ksm-box__hint">Per ogni settimana di prima visita, la quota di persone che si rivede nelle settimane dopo.</p>

        @if (count($cohorts) === 0)
            <p class="ksm-muted" style="margin: 0;">{{ $returningEnabled ? 'I dati arrivano dopo le prime settimane di visite con il cookie attivo.' : 'Serve il cookie dei visitatori di ritorno (vedi a fianco).' }}</p>
        @else
            <div class="ksm-table-wrap ksm-table-wrap--flush ksm-table-wrap--scroll">
                <table class="ksm-table ksm-table--stats ksm-table--cohort" data-flat>
                    <thead><tr><th>Settimana</th><th class="is-num">Persone</th>@foreach (range(0, max(array_map(fn ($c) => count($c['weeks']), $cohorts)) - 1) as $w)<th class="is-num">+{{ $w }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach ($cohorts as $cohort)
                        <tr>
                            <td>{{ $cohort['label'] }}</td>
                            <td class="is-num">{{ Format::number($cohort['size']) }}</td>
                            @foreach ($cohort['weeks'] as $share)
                                <td class="is-num"><span class="ksm-heat__cell ksm-cohort" style="--v: {{ round($share, 2) }}">{{ Format::percent($share) }}</span></td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

<div class="ksm-panel-grid ksm-panel-grid--even" id="regioni">
    <section class="ksm-box">
        <div class="ksm-box__head"><h2>Regioni italiane</h2></div>

        @if ($regions->isEmpty())
            <p class="ksm-box__hint" style="margin: 0;">
                @if ($hasPlaces)
                    Nessuna visita da regioni italiane in questo periodo.
                @else
                    Regione e città si leggono da un archivio gratuito degli indirizzi IP (DB-IP City Lite) da caricare sul server:
                    <code>php artisan analytics:geo-update</code> lo scarica, oppure si carica a mano il file in
                    <code>storage/app/geoip/dbip-city-lite.mmdb</code>. Vale solo per le visite dopo il caricamento.
                @endif
            </p>
        @else
            <ul class="ksm-meterlist">
                @foreach ($regions as $row)
                    <li>
                        <span class="ksm-meterlist__top">
                            <span>{{ $row->label }}</span>
                            <span class="ksm-muted">{{ Format::number($row->count) }} · {{ Format::percent($row->share) }}</span>
                        </span>
                        <span class="ksm-meter"><i style="width: {{ round($row->share * 100) }}%"></i></span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="ksm-box">
        <div class="ksm-box__head"><h2>Città italiane</h2></div>

        @if ($cities->isEmpty())
            <p class="ksm-muted" style="margin: 0;">Nessuna città rilevata.</p>
        @else
            <ul class="ksm-meterlist">
                @foreach ($cities as $row)
                    <li>
                        <span class="ksm-meterlist__top">
                            <span>{{ $row->label }}</span>
                            <span class="ksm-muted">{{ Format::number($row->count) }} · {{ Format::percent($row->share) }}</span>
                        </span>
                        <span class="ksm-meter"><i style="width: {{ round($row->share * 100) }}%"></i></span>
                    </li>
                @endforeach
            </ul>
            <p class="ksm-box__hint" style="margin: 12px 0 0;">La città dall'indirizzo IP è approssimata (spesso è quella del fornitore di rete). Dati geografici: <a href="https://db-ip.com" target="_blank" rel="noopener">IP Geolocation by DB-IP</a>.</p>
        @endif
    </section>
</div>
