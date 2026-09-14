@extends('layouts.panel')

@section('title', 'Inserzionisti · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>Inserzionisti</h1>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.advertisements.index') }}">Campagne</a>
            <a class="ksm-btn ksm-btn--primary" href="{{ route('admin.advertisers.create') }}">Nuovo inserzionista</a>
        </div>
    </div>

    <p class="ksm-muted">
        Gli esterni possono registrarsi da soli su
        <a href="{{ route('advertiser.register') }}" target="_blank" rel="noopener">{{ route('advertiser.register') }}</a>.
    </p>

    <form method="GET" class="ksm-filters">
        <input class="ksm-input" name="cerca" value="{{ request('cerca') }}" placeholder="Nome o email">
        <button class="ksm-btn ksm-btn--ghost" type="submit">Cerca</button>
    </form>

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
