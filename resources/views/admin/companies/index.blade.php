@extends('layouts.panel')

@section('title', 'Aziende · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>Aziende</h1>
        @can(\App\Support\Permissions::COMPANIES_MANAGE)
            <a class="ksm-btn ksm-btn--primary" href="{{ route('admin.companies.create') }}">Nuova azienda</a>
        @endcan
    </div>

    <form method="GET" class="ksm-filters">
        <input class="ksm-input" name="cerca" value="{{ request('cerca') }}" placeholder="Nome o email">

        <select class="ksm-select" name="piano" aria-label="Piano">
            <option value="">Tutti i piani</option>
            @foreach ($plans as $id => $name)
                <option value="{{ $id }}" @selected(request('piano') == $id)>{{ $name }}</option>
            @endforeach
        </select>

        <select class="ksm-select" name="stato" aria-label="Stato">
            <option value="">Attive e spente</option>
            <option value="attive" @selected(request('stato') === 'attive')>Solo attive</option>
            <option value="spente" @selected(request('stato') === 'spente')>Solo spente</option>
        </select>

        <button class="ksm-btn ksm-btn--ghost" type="submit">Filtra</button>
    </form>

    <p class="ksm-muted">{{ number_format($companies->total(), 0, ',', '.') }} aziende</p>

    <div class="ksm-table-wrap">
        <table class="ksm-table ksm-table--companies">
            <thead>
            <tr>
                <th aria-label="Logo"></th>
                <th>Azienda</th>
                <th>Email</th>
                <th>Telefono</th>
                <th>Piano</th>
                <th>Stato</th>
                <th>Creata</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($companies as $company)
                <tr>
                    <td>
                        <span class="ksm-thumb"
                              @if ($company->logo) style="background-image: url('{{ asset('storage/'.$company->logo) }}')" @endif></span>
                    </td>
                    <td class="ksm-table__name">
                        <strong>{{ $company->name }}</strong>
                        @if ($company->city)
                            <br><small class="ksm-muted">{{ $company->city }}</small>
                        @endif
                    </td>
                    <td class="ksm-table__email">{{ $company->email ?: '—' }}</td>
                    <td class="ksm-table__phone">{{ $company->phone ?: '—' }}</td>
                    <td>
                        @if ($company->plan)
                            <span class="ksm-badge">{{ $company->plan->name }}</span>
                        @else
                            <span class="ksm-badge ksm-badge--muted">Nessuno</span>
                        @endif
                    </td>
                    <td>
                        <span class="ksm-badge @unless ($company->is_active) ksm-badge--muted @endunless">
                            {{ $company->is_active ? 'Attiva' : 'Spenta' }}
                        </span>
                    </td>
                    <td style="white-space: nowrap;">{{ $company->created_at?->format('d/m/Y') }}</td>
                    <td style="text-align: right; white-space: nowrap;">
                        @if ($company->hasPage())
                            <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" target="_blank" rel="noopener"
                               href="{{ route('companies.show', ['company' => $company->slug]) }}">Vedi</a>
                        @endif
                        @can(\App\Support\Permissions::COMPANIES_MANAGE)
                            <a class="ksm-btn ksm-btn--ghost ksm-btn--sm"
                               href="{{ route('admin.companies.edit', $company) }}">Modifica</a>
                            <form method="POST" action="{{ route('admin.companies.status', $company) }}" style="display: inline;">
                                @csrf @method('PATCH')
                                <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">
                                    {{ $company->is_active ? 'Spegni' : 'Accendi' }}
                                </button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="ksm-muted">Nessuna azienda.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $companies->links() }}</div>
@endsection
