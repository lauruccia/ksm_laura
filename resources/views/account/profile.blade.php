@extends('layouts.panel')

@section('title', 'Dati e indirizzo · '.$tenant->brandName())
@section('role', 'Il mio account')
@section('crumb', 'Dati e indirizzo')
@section('nav')@include('account.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>Dati e indirizzo</h1>
    </div>

    <form class="ksm-box" style="max-width: 760px;" method="POST" action="{{ route('account.profile.update') }}">
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

            <div class="ksm-field">
                <label class="ksm-label" for="phone">Telefono</label>
                <input class="ksm-input" id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
            </div>
        </div>

        <h2 class="ksm-box__subhead">Indirizzo di consegna</h2>
        <p class="ksm-muted" style="margin: -6px 0 16px;">
            Lo usiamo per compilare la cassa. Gli ordini già fatti non cambiano.
        </p>

        <div class="ksm-field">
            <label class="ksm-label" for="billing_address">Indirizzo</label>
            <input class="ksm-input" id="billing_address" name="billing_address"
                   value="{{ old('billing_address', $user->billing_address) }}">
        </div>

        <div class="ksm-formgrid">
            <div class="ksm-field">
                <label class="ksm-label" for="billing_city">Città</label>
                <input class="ksm-input" id="billing_city" name="billing_city"
                       value="{{ old('billing_city', $user->billing_city) }}">
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="billing_state">Provincia</label>
                <input class="ksm-input" id="billing_state" name="billing_state"
                       value="{{ old('billing_state', $user->billing_state) }}">
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="billing_zip">CAP</label>
                <input class="ksm-input" id="billing_zip" name="billing_zip"
                       value="{{ old('billing_zip', $user->billing_zip) }}">
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="billing_country">Paese</label>
                <input class="ksm-input" id="billing_country" name="billing_country"
                       value="{{ old('billing_country', $user->billing_country) }}">
            </div>
        </div>

        <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
    </form>

    <form class="ksm-box" style="max-width: 760px;" method="POST" action="{{ route('account.profile.password') }}">
        @csrf @method('PUT')

        <div class="ksm-box__head"><h2>Cambio password</h2></div>

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

        <button class="ksm-btn ksm-btn--primary" type="submit">Cambia password</button>
    </form>
@endsection
