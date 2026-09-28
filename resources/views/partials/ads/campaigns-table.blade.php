{{-- Elenco campagne con conti e stato. Serve $campaigns e $detailRoute, la rotta del dettaglio. --}}
<div class="ksm-table-wrap">
    <table class="ksm-table ksm-table--campaigns">
        <thead>
        <tr>
            <th>Campagna</th>
            <th>Periodo</th>
            <th class="is-num">Visualizzazioni</th>
            <th class="is-num">Clic</th>
            <th class="is-num">CTR</th>
            <th>Stato</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($campaigns as $campaign)
            <tr>
                @php
                    // Una posizione sola si legge per intero; piu' posizioni diventano le pagine,
                    // con quante su ciascuna ("Home ×4 · Catalogo"). L'elenco completo e' nel suggerimento.
                    $labels = collect($campaign->locations)->map(fn ($key) => \App\Support\Ads\Placements::label($key));
                    $places = $labels->count() === 1
                        ? $labels
                        : $labels->groupBy(fn ($label) => \Illuminate\Support\Str::before($label, ','))
                            ->map(fn ($group, $page) => $group->count() > 1 ? $page.' ×'.$group->count() : $page);
                @endphp
                <td class="ksm-campaign">
                    <strong>{{ $campaign->name }}</strong>
                    @if ($places->isNotEmpty())
                        <small class="ksm-campaign__places" title="{{ $labels->join("\n") }}">{{ $places->join(' · ') }}</small>
                    @endif
                </td>
                <td>
                    {{ $campaign->starts_at?->format('d/m/Y') ?? 'subito' }}
                    →
                    {{ $campaign->ends_at?->format('d/m/Y') ?? 'senza scadenza' }}
                    <small class="ksm-campaign__billing">{{ \App\Models\Advertisement::BILLING[$campaign->billing] ?? $campaign->billing }}</small>
                </td>
                <td class="is-num">
                    {{ number_format($campaign->impressions, 0, ',', '.') }}
                    @if ($campaign->max_impressions)
                        / {{ number_format($campaign->max_impressions, 0, ',', '.') }}
                    @endif
                </td>
                <td class="is-num">
                    {{ number_format($campaign->clicks, 0, ',', '.') }}
                    @if ($campaign->max_clicks)
                        / {{ number_format($campaign->max_clicks, 0, ',', '.') }}
                    @endif
                </td>
                <td class="is-num">{{ number_format($campaign->ctr(), 2, ',', '.') }}%</td>
                <td>
                    <span class="ksm-badge @unless ($campaign->isRunning()) ksm-badge--muted @endunless">
                        {{ $campaign->stateLabel() }}
                    </span>
                </td>
                <td>
                    <div class="ksm-rowactions">
                        <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route($detailRoute, $campaign) }}">Statistiche</a>
                        @isset($editRoute)
                            <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route($editRoute, $campaign) }}">Modifica</a>
                        @endisset
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="ksm-muted">Nessuna campagna.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
