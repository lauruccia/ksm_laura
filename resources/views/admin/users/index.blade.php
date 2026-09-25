@php use App\Support\Permissions as P; @endphp

@extends('layouts.panel')

@section('title', 'Utenti · KSM')
@section('role', 'Amministrazione')
@section('crumb', 'Utenti')
@section('nav')@include('admin.nav')@endsection

@section('content')
    {{-- Titolo, numero e filtri su una riga sola: l'elenco parte subito sotto. --}}
    <div class="ksm-listhead">
        <h1>Utenti <span class="ksm-listhead__count">{{ number_format($users->total(), 0, ',', '.') }}</span></h1>
        <span class="ksm-muted" style="font-size: .85rem;">
            {{ $counts['admin'] }} in amministrazione, {{ $counts['vendor'] }} aziende, {{ $counts['buyer'] }} clienti
        </span>

        <form method="GET" class="ksm-listhead__filters" role="search">
            <span class="ksm-listhead__search">
                <x-icon name="search" :size="16" />
                <input class="ksm-input" type="search" name="cerca" value="{{ request('cerca') }}" placeholder="Nome o email" aria-label="Nome o email">
            </span>
            <select class="ksm-select" name="tipo" aria-label="Tipo" onchange="this.form.submit()">
                <option value="">Tutti i tipi</option>
                @foreach ($types as $key => $label)
                    <option value="{{ $key }}" @selected(request('tipo') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="ksm-select" name="ruolo" aria-label="Ruolo" onchange="this.form.submit()">
                <option value="">Tutti i ruoli</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((string) request('ruolo') === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Cerca</button>
            @if (request()->hasAny(['cerca', 'tipo', 'ruolo']))
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.users.index') }}">Azzera</a>
            @endif
        </form>

        @can(P::USERS_MANAGE)
            <span class="ksm-listhead__actions">
                <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route('admin.users.create') }}">Nuovo utente</a>
            </span>
        @endcan
    </div>

    @if (auth()->user()->can(P::USERS_MANAGE) && $users->isNotEmpty())
        @include('partials.bulk-bar', [
            'action' => route('admin.users.bulk'),
            'paginator' => $users,
            'noun' => 'utenti',
            'nounOne' => 'utente',
            'actions' => ['activate' => 'Riattiva', 'deactivate' => 'Sospendi', 'delete' => 'Elimina'],
            'onlySelected' => ['delete'],
        ])
    @endif

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead>
            <tr>
                @can(P::USERS_MANAGE)
                    <th class="ksm-bulk__cell"><input type="checkbox" data-bulk-page aria-label="Seleziona tutti in questa pagina"></th>
                @endcan
                <th>Nome</th>
                <th>Email</th>
                <th>Tipo</th>
                <th>Ruolo</th>
                <th>Accesso</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($users as $user)
                <tr>
                    @can(P::USERS_MANAGE)
                        <td class="ksm-bulk__cell">
                            {{-- Il proprio accesso non si sospende ne' si elimina: niente casella. --}}
                            @unless ($user->is(auth()->user()))
                                <input type="checkbox" name="ids[]" value="{{ $user->id }}" form="bulk" data-bulk-item
                                       aria-label="Seleziona {{ $user->name }}">
                            @endunless
                        </td>
                    @endcan
                    <td>
                        <strong>{{ $user->name }}</strong>
                        @if ($user->is(auth()->user()))
                            <span class="ksm-badge ksm-badge--muted">tu</span>
                        @endif
                    </td>
                    <td class="ksm-muted">{{ $user->email }}</td>
                    <td><span class="ksm-badge ksm-badge--{{ $user->user_type }}">{{ $user->typeLabel() }}</span></td>
                    <td class="ksm-muted">{{ $user->role?->name ?? '—' }}</td>
                    <td>
                        <span class="ksm-badge @if (! $user->is_active) ksm-badge--muted @endif">
                            {{ $user->is_active ? 'Attivo' : 'Sospeso' }}
                        </span>
                    </td>
                    <td class="ksm-rowactions">
                        @can(P::USERS_MANAGE)
                            <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.users.edit', $user) }}">Modifica</a>

                            @unless ($user->is(auth()->user()))
                                <form method="POST" action="{{ route('admin.users.status', $user) }}">
                                    @csrf @method('PATCH')
                                    <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">
                                        {{ $user->is_active ? 'Sospendi' : 'Riattiva' }}
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                      onsubmit="return confirm('Eliminare {{ $user->name }}?');">
                                    @csrf @method('DELETE')
                                    <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Elimina</button>
                                </form>
                            @endunless
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="ksm-muted">Nessun utente con questi filtri.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $users->links() }}</div>
@endsection
