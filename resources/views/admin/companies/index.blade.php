@extends('layouts.panel')

@section('title', 'Aziende · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    @php($canManage = auth()->user()->can(\App\Support\Permissions::COMPANIES_MANAGE))

    {{-- Titolo, numero e filtri su una riga sola: l'elenco parte subito sotto. --}}
    <div class="ksm-listhead">
        <h1>Aziende <span class="ksm-listhead__count">{{ number_format($companies->total(), 0, ',', '.') }}</span></h1>

        <form method="GET" class="ksm-listhead__filters" role="search">
            <span class="ksm-listhead__search">
                <x-icon name="search" :size="16" />
                <input class="ksm-input" type="search" name="cerca" value="{{ request('cerca') }}" placeholder="Nome o email" aria-label="Nome o email">
            </span>
            <select class="ksm-select" name="piano" aria-label="Piano" onchange="this.form.submit()">
                <option value="">Tutti i piani</option>
                @foreach ($plans as $id => $name)
                    <option value="{{ $id }}" @selected(request('piano') == $id)>{{ $name }}</option>
                @endforeach
            </select>
            <select class="ksm-select" name="stato" aria-label="Stato" onchange="this.form.submit()">
                <option value="">Attive e spente</option>
                <option value="attive" @selected(request('stato') === 'attive')>Solo attive</option>
                <option value="spente" @selected(request('stato') === 'spente')>Solo spente</option>
            </select>
            <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Cerca</button>
            @if (request()->hasAny(['cerca', 'piano', 'stato']))
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.companies.index') }}">Azzera</a>
            @endif
        </form>

        @if ($canManage)
            <span class="ksm-listhead__actions">
                <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route('admin.companies.create') }}">Nuova azienda</a>
            </span>
        @endif
    </div>

    @if ($canManage && $companies->isNotEmpty())
        @include('partials.bulk-bar', [
            'action' => route('admin.companies.bulk'),
            'paginator' => $companies,
            'noun' => 'aziende',
            'nounOne' => 'azienda',
            'feminine' => true,
            'actions' => ['activate' => 'Accendi', 'deactivate' => 'Spegni', 'delete' => 'Elimina'],
            'onlySelected' => ['delete'],
        ])
    @endif

    <div class="ksm-table-wrap">
        <table class="ksm-table ksm-table--companies">
            <thead>
            <tr>
                @if ($canManage)
                    <th class="ksm-bulk__cell"><input type="checkbox" data-bulk-page aria-label="Seleziona tutte in questa pagina"></th>
                @endif
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
                    @if ($canManage)
                        <td class="ksm-bulk__cell">
                            <input type="checkbox" name="ids[]" value="{{ $company->id }}" form="bulk" data-bulk-item
                                   aria-label="Seleziona {{ $company->name }}">
                        </td>
                    @endif
                    <td class="ksm-table__logo">
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
                    <td class="ksm-table__phone">
                        @if ($company->phone)
                            {{-- Molte anagrafiche importate hanno piu' numeri in un campo solo:
                                 in elenco se ne mostrano due righe, il resto nel suggerimento. --}}
                            <span class="ksm-clamp" title="{{ $company->phone }}">{{ $company->phone }}</span>
                        @else
                            —
                        @endif
                    </td>
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
                    <td>
                        <div class="ksm-rowactions">
                            @if ($company->hasPage())
                                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" target="_blank" rel="noopener"
                                   href="{{ route('companies.show', ['company' => $company->slug]) }}">Vedi</a>
                            @endif
                            @if ($canManage)
                                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm"
                                   href="{{ route('admin.companies.edit', $company) }}">Modifica</a>
                                <form method="POST" action="{{ route('admin.companies.status', $company) }}">
                                    @csrf @method('PATCH')
                                    <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">
                                        {{ $company->is_active ? 'Spegni' : 'Accendi' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $canManage ? 9 : 8 }}" class="ksm-muted">Nessuna azienda.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $companies->links() }}</div>
@endsection
