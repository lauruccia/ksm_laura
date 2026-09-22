@extends('layouts.panel')

@section('title', ($user->exists ? $user->name : 'Nuovo utente').' · KSM')
@section('role', 'Amministrazione')
@section('crumb', 'Utenti')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>{{ $user->exists ? $user->name : 'Nuovo utente' }}</h1>
        <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.users.index') }}">Torna all'elenco</a>
    </div>

    <form class="ksm-box" style="max-width: 780px;" method="POST"
          action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <div class="ksm-formgrid">
            <div class="ksm-field">
                <label class="ksm-label" for="name">Nome e cognome</label>
                <input class="ksm-input" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                @error('name')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="email">Email</label>
                <input class="ksm-input" id="email" name="email" type="email"
                       value="{{ old('email', $user->email) }}" required>
                @error('email')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="phone">Telefono</label>
                <input class="ksm-input" id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
                @error('phone')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="user_type">Tipo</label>
                <select class="ksm-select" id="user_type" name="user_type">
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}" @selected(old('user_type', $user->user_type) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <small class="ksm-muted">Il ruolo vale solo per chi entra in amministrazione.</small>
                @error('user_type')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="ksm-field">
            <label class="ksm-label" for="role_id">Ruolo in amministrazione</label>
            <select class="ksm-select" id="role_id" name="role_id">
                <option value="">Nessuno</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id) === (string) $role->id)>
                        {{ $role->name }}@if ($role->description) — {{ $role->description }}@endif
                    </option>
                @endforeach
            </select>
            <small class="ksm-muted">Obbligatorio per il tipo Amministrazione: decide cosa si vede nel pannello.</small>
            @error('role_id')<span class="ksm-error">{{ $message }}</span>@enderror
        </div>

        <div class="ksm-field">
            <label class="ksm-label" style="display: flex; gap: 8px; align-items: center;">
                <input type="hidden" name="is_active" value="0">
                <input name="is_active" type="checkbox" value="1"
                       @checked(old('is_active', $user->exists ? $user->is_active : true))
                       @disabled($user->is(auth()->user()))>
                Accesso attivo
            </label>
            @if ($user->is(auth()->user()))
                <small class="ksm-muted">Tipo, ruolo e stato del proprio accesso si cambiano da un altro amministratore.</small>
            @endif
        </div>

        <div class="ksm-formgrid">
            <div class="ksm-field">
                <label class="ksm-label" for="password">Password</label>
                <input class="ksm-input" id="password" name="password" type="password" autocomplete="new-password">
                <small class="ksm-muted">{{ $user->exists ? 'Lascia vuoto per non cambiarla.' : 'Comunicala di persona, non per email.' }}</small>
                @error('password')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="password_confirmation">Conferma password</label>
                <input class="ksm-input" id="password_confirmation" name="password_confirmation"
                       type="password" autocomplete="new-password">
            </div>
        </div>

        <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
    </form>

    @if ($user->exists && $user->role)
        <section class="ksm-box" style="max-width: 780px; margin-top: 22px;">
            <div class="ksm-box__head"><h2>Cosa concede il ruolo {{ $user->role->name }}</h2></div>
            <ul class="ksm-taglist">
                @foreach ($user->role->permissionLabels() as $label)
                    <li>{{ $label }}</li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
