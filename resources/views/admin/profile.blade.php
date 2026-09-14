@extends('layouts.panel')

@section('title', 'Il mio profilo · KSM')
@section('role', 'Amministrazione')
@section('crumb', 'Il mio profilo')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>Il mio profilo</h1>
        <span class="ksm-badge">{{ $user->role?->name ?? 'nessun ruolo' }}</span>
    </div>

    <form class="ksm-box" style="max-width: 700px;" method="POST" action="{{ route('admin.profile.update') }}">
        @csrf @method('PUT')

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
        </div>

        <div class="ksm-field">
            <label class="ksm-label" for="phone">Telefono</label>
            <input class="ksm-input" id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
        </div>

        <h2 class="ksm-box__subhead">Cambio password</h2>

        <div class="ksm-field">
            <label class="ksm-label" for="current_password">Password attuale</label>
            <input class="ksm-input" id="current_password" name="current_password" type="password"
                   autocomplete="current-password">
            @error('current_password')<span class="ksm-error">{{ $message }}</span>@enderror
        </div>

        <div class="ksm-formgrid">
            <div class="ksm-field">
                <label class="ksm-label" for="password">Nuova password</label>
                <input class="ksm-input" id="password" name="password" type="password" autocomplete="new-password">
                @error('password')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="password_confirmation">Conferma</label>
                <input class="ksm-input" id="password_confirmation" name="password_confirmation"
                       type="password" autocomplete="new-password">
            </div>
        </div>

        <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
    </form>

    @if ($user->role)
        <section class="ksm-box" style="max-width: 700px; margin-top: 22px;">
            <div class="ksm-box__head"><h2>Cosa posso fare</h2></div>
            <ul class="ksm-taglist">
                @foreach ($user->role->permissionLabels() as $label)
                    <li>{{ $label }}</li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
