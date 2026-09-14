{{-- Elenco campagne con conti e stato. Serve $campaigns e $detailRoute, la rotta del dettaglio. --}}
<div class="ksm-table-wrap">
    <table class="ksm-table">
        <thead>
        <tr>
            <th>Campagna</th>
            <th>Tipo</th>
            <th>Periodo</th>
            <th>Visualizzazioni</th>
            <th>Clic</th>
            <th>CTR</th>
            <th>Stato</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($campaigns as $campaign)
            <tr>
                <td>
                    <strong>{{ $campaign->name }}</strong>
                    <br><small class="ksm-muted">
                        {{ collect($campaign->locations)->map(fn ($key) => \App\Support\Ads\Placements::label($key))->join(', ') }}
                    </small>
                </td>
                <td>{{ \App\Models\Advertisement::BILLING[$campaign->billing] ?? $campaign->billing }}</td>
                <td style="white-space: nowrap;">
                    {{ $campaign->starts_at?->format('d/m/Y') ?? 'subito' }}
                    →
                    {{ $campaign->ends_at?->format('d/m/Y') ?? 'senza scadenza' }}
                </td>
                <td style="white-space: nowrap;">
                    {{ number_format($campaign->impressions, 0, ',', '.') }}
                    @if ($campaign->max_impressions)
                        / {{ number_format($campaign->max_impressions, 0, ',', '.') }}
                    @endif
                </td>
                <td style="white-space: nowrap;">
                    {{ number_format($campaign->clicks, 0, ',', '.') }}
                    @if ($campaign->max_clicks)
                        / {{ number_format($campaign->max_clicks, 0, ',', '.') }}
                    @endif
                </td>
                <td>{{ number_format($campaign->ctr(), 2, ',', '.') }}%</td>
                <td>
                    <span class="ksm-badge @unless ($campaign->isRunning()) ksm-badge--muted @endunless">
                        {{ $campaign->stateLabel() }}
                    </span>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                    <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route($detailRoute, $campaign) }}">Statistiche</a>
                    @isset($editRoute)
                        <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route($editRoute, $campaign) }}">Modifica</a>
                    @endisset
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="ksm-muted">Nessuna campagna.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
