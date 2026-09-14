{{-- Riepilogo di una campagna e conti degli ultimi giorni. Serve $campaign e $stats. --}}
<section class="ksm-box" style="margin-bottom: 22px;">
    <div class="ksm-box__head"><h2>{{ $campaign->name }}</h2></div>

    <div class="ksm-grid ksm-grid--2">
        <ul class="ksm-meta">
            <li>Stato: <strong>{{ $campaign->stateLabel() }}</strong></li>
            <li>Tipo: {{ \App\Models\Advertisement::BILLING[$campaign->billing] ?? $campaign->billing }}</li>
            <li>
                Periodo: {{ $campaign->starts_at?->format('d/m/Y') ?? 'subito' }}
                → {{ $campaign->ends_at?->format('d/m/Y') ?? 'senza scadenza' }}
            </li>
            <li>
                Posizioni:
                {{ collect($campaign->locations)->map(fn ($key) => \App\Support\Ads\Placements::label($key))->join(', ') }}
            </li>
        </ul>

        <ul class="ksm-meta">
            <li>
                Visualizzazioni: <strong>{{ number_format($campaign->impressions, 0, ',', '.') }}</strong>
                @if ($campaign->max_impressions) di {{ number_format($campaign->max_impressions, 0, ',', '.') }} acquistate @endif
            </li>
            <li>
                Clic: <strong>{{ number_format($campaign->clicks, 0, ',', '.') }}</strong>
                @if ($campaign->max_clicks) di {{ number_format($campaign->max_clicks, 0, ',', '.') }} acquistati @endif
            </li>
            <li>CTR: {{ number_format($campaign->ctr(), 2, ',', '.') }}%</li>
            <li>Collegamento: <a href="{{ $campaign->link }}" target="_blank" rel="noopener">{{ $campaign->link }}</a></li>
        </ul>
    </div>

    @if ($campaign->img)
        <img src="{{ asset('storage/'.$campaign->img) }}" alt="{{ $campaign->name }}"
             style="max-width: 100%; max-height: 220px; margin-top: 14px; border-radius: 8px;">
    @endif
</section>

<section class="ksm-box">
    <div class="ksm-box__head"><h2>Ultimi 60 giorni</h2></div>

    <p class="ksm-muted" style="margin-top: 0; font-size: .88rem;">
        Una visualizzazione conta quando il banner resta sullo schermo almeno un secondo; la stessa persona
        conta una volta ogni mezz'ora, e i clic una volta all'ora. Visualizzazioni e clic dei programmi
        automatici non si pagano: sono nella colonna degli scartati.
    </p>

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead>
            <tr><th>Giorno</th><th>Visualizzazioni</th><th>Clic</th><th>CTR</th><th>Scartati come automatici</th></tr>
            </thead>
            <tbody>
            @forelse ($stats as $row)
                <tr>
                    <td>{{ $row->day->format('d/m/Y') }}</td>
                    <td>{{ number_format($row->impressions, 0, ',', '.') }}</td>
                    <td>{{ number_format($row->clicks, 0, ',', '.') }}</td>
                    <td>{{ number_format($row->ctr(), 2, ',', '.') }}%</td>
                    <td class="ksm-muted">
                        {{ number_format($row->filtered_impressions, 0, ',', '.') }} visualizzazioni,
                        {{ number_format($row->filtered_clicks, 0, ',', '.') }} clic
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="ksm-muted">Ancora nessuna visualizzazione.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
