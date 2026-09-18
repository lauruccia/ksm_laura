@extends('layouts.app')

@section('title', __('site.sign_in'))

@section('content')
    <x-auth.shell :title="__('site.sign_in')" lead="Un solo accesso per privati e aziende: ti portiamo nella tua area.">
        <x-slot:aside>
            <p class="ksm-auth__eyebrow">Nuovo su {{ $tenant->brandName() }}?</p>
            <h2 class="ksm-auth__aside-title">Crea il tuo account</h2>

            <a class="ksm-auth__option" href="{{ route('register.buyer') }}">
                <span class="ksm-auth__option-icon"><x-icon name="user" :size="22" /></span>
                <span>
                    <strong>Sono un privato</strong>
                    <small>Compro prodotti e seguo i miei ordini</small>
                </span>
            </a>

            <a class="ksm-auth__option" href="{{ route('register.vendor') }}">
                <span class="ksm-auth__option-icon"><x-icon name="building" :size="22" /></span>
                <span>
                    <strong>Sono un'azienda</strong>
                    <small>Porto la mia attività nel marketplace</small>
                </span>
            </a>
        </x-slot:aside>

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <div class="ksm-field">
                <label class="ksm-label" for="email">Email</label>
                <input class="ksm-input" id="email" name="email" type="email" value="{{ old('email') }}"
                       autocomplete="email" required autofocus>
                @error('email')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-field">
                <div class="ksm-auth__label-row">
                    <label class="ksm-label" for="password">Password</label>
                    <a href="{{ route('password.request') }}">Password dimenticata?</a>
                </div>
                <input class="ksm-input" id="password" name="password" type="password" autocomplete="current-password" required>
                @error('password')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <label class="ksm-auth__check">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))> Ricordami su questo dispositivo
            </label>

            <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">{{ __('site.sign_in') }}</button>
        </form>

        <p class="ksm-auth__foot">
            Non hai un account? <a href="{{ route('register') }}">Registrati</a>
        </p>
    </x-auth.shell>
@endsection
