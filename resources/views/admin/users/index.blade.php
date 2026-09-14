@php use App\Support\Permissions as P; @endphp

@extends('layouts.panel')

@section('title', 'Utenti · KSM')
@section('role', 'Amministrazione')
@section('crumb', 'Utenti')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <div>
            <h1>Utenti</h1>
            <p class="ksm-muted" style="margin: 4px 0 0;">
                {{ $counts['admin'] }} in amministrazione, {{ $counts['vendor'] }} aziende,
                {{ $counts['buyer'] }} clienti.
            </p>
        </div>
        @can(P::USERS_MANAGE)
            <a class="ksm-btn ksm-btn--primary" href="{{ route('admin.users.create') }}">Nuovo utente</a>
        @endcan
    </div>

    <form class="ksm-filters" method="GET">
        <input class="ksm-input" name="cerca" value="{{ request('cerca') }}" placeholder="Nome o email">

        <select class="ksm-select" name="tipo">
            <option value="">Tutti i tipi</option>
            @foreach ($types as $key => $label)
                <option value="{{ $key }}" @selected(request('tipo') === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <select class="ksm-select" name="ruolo">
            <option value="">Tutti i ruoli</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) request('ruolo') === (string) $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>

        <button class="ksm-btn ksm-btn--ghost" type="submit">Filtra</button>
        @if (request()->hasAny(['cerca', 'tipo', 'ruolo']))
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.users.index') }}">Azzera</a>
        @endif
    </form>

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead>
            <tr>
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
                <tr><td colspan="6" class="ksm-muted">Nessun utente con questi filtri.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $users->links() }}</div>
@endsection
