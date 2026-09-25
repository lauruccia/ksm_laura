@extends('layouts.panel')

@section('title', 'Inserzionisti · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    {{-- Titolo, numero e ricerca su una riga sola: l'elenco parte subito sotto. --}}
    <div class="ksm-listhead">
        <h1>Inserzionisti <span class="ksm-listhead__count">{{ number_format($advertisers->total(), 0, ',', '.') }}</span></h1>

        <form method="GET" class="ksm-listhead__filters" role="search">
            <span class="ksm-listhead__search">
                <x-icon name="search" :size="16" />
                <input class="ksm-input" type="search" name="cerca" value="{{ request('cerca') }}" placeholder="Nome o email" aria-label="Nome o email">
            </span>
            <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Cerca</button>
            @if (request()->filled('cerca'))
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.advertisers.index') }}">Azzera</a>
            @endif
        </form>

        <span class="ksm-listhead__actions">
            <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.advertisements.index') }}">Campagne</a>
            <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route('admin.advertisers.create') }}">Nuovo inserzionista</a>
        </span>
    </div>

    <p class="ksm-muted" style="margin: -4px 0 14px; font-size: .85rem;">
        Gli esterni possono registrarsi da soli su
        <a href="{{ route('advertiser.register') }}" target="_blank" rel="noopener">{{ route('advertiser.register') }}</a>.
    </p>

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead>
            <tr><th>Inserzionista</th><th>Tipo</th><th>Accesso</th><th>Campagne</th><th>Stato</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($advertisers as $advertiser)
                <tr>
                    <td>
                        <strong>{{ $advertiser->name }}</strong>
                        @if ($advertiser->email)<br><small class="ksm-muted">{{ $advertiser->email }}</small>@endif
                    </td>
                    <td>{{ $advertiser->kindLabel() }}</td>
                    <td>
                        {{ $advertiser->isCompany() ? 'area azienda' : ($advertiser->user?->email ?? '—') }}
                    </td>
                    <td>{{ $advertiser->advertisements_count }}</td>
                    <td>
                        <span class="ksm-badge @unless ($advertiser->is_active) ksm-badge--muted @endunless">
                            {{ $advertiser->is_active ? 'Attivo' : 'Sospeso' }}
                        </span>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                        <a class="ksm-btn ksm-btn--ghost ksm-btn--sm"
                           href="{{ route('admin.advertisements.index', ['inserzionista' => $advertiser->id]) }}">Campagne</a>
                        <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.advertisers.edit', $advertiser) }}">Modifica</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="ksm-muted">Nessun inserzionista.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $advertisers->links() }}</div>
@endsection
