@extends('layouts.app')

@section('title', __('site.register_company'))

@section('content')
    <x-auth.shell :title="__('site.register_company')" lead="Crea l'accesso del referente: il profilo dell'azienda lo completi subito dopo.">
        <x-slot:aside>
            <p class="ksm-auth__eyebrow">Account azienda</p>
            <h2 class="ksm-auth__aside-title">Come funziona</h2>

            <ol class="ksm-auth__steps">
                <li class="is-current"><strong>Account del referente</strong><small>Nome, email e password</small></li>
                <li><strong>Verifica email</strong><small>Ti mandiamo un codice</small></li>
                <li><strong>Profilo aziendale</strong><small>Ragione sociale, sede, contatti</small></li>
                <li><strong>Piano</strong><small>Attivi quello scelto, anche gratuito</small></li>
            </ol>

            <p class="ksm-auth__aside-note">
                Vuoi solo comprare? <a href="{{ route('register.buyer') }}">Registrati come privato</a>
            </p>
        </x-slot:aside>

        <x-auth.switch active="vendor" />

        <form method="POST" action="{{ route('register.vendor.store') }}">
            @csrf

            <div class="ksm-field">
                <label class="ksm-label" for="name">Nome e cognome del referente</label>
                <input class="ksm-input" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>
                @error('name')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-auth__row">
                <div class="ksm-field">
                    <label class="ksm-label" for="email">Email aziendale</label>
                    <input class="ksm-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                    @error('email')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="phone">Telefono <span class="ksm-auth__optional">facoltativo</span></label>
                    <input class="ksm-input" id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel">
                    @error('phone')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
            </div>

            @if ($plans->isNotEmpty())
                <fieldset class="ksm-field ksm-auth__plans">
                    <legend class="ksm-label">Piano</legend>

                    <div class="ksm-auth__plan-grid">
                        @foreach ($plans as $plan)
                            <label class="ksm-auth__plan">
                                <input type="radio" name="piano" value="{{ $plan->slug }}"
                                       @checked(old('piano', $chosen) === $plan->slug)>
                                <span>
                                    <strong>{{ $plan->name }}</strong>
                                    <small>{{ $plan->isFree() ? 'Gratis' : \App\Support\Money::format($plan->price) }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <p class="ksm-auth__hint">
                        <label class="ksm-auth__plan-later">
                            <input type="radio" name="piano" value="" @checked(blank(old('piano', $chosen)))>
                            <span>Decido dopo</span>
                        </label>
                        &middot; Lo attivi dopo la verifica dell'email.
                        <a href="{{ route('plans.index') }}" target="_blank" rel="noopener">Confronta i piani</a>
                    </p>
                    @error('piano')<span class="ksm-error">{{ $message }}</span>@enderror
                </fieldset>
            @endif

            <x-auth.password-fields />

            <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">Crea l'account azienda</button>
        </form>

        <p class="ksm-auth__foot">Hai già un account? <a href="{{ route('login') }}">Accedi</a></p>
    </x-auth.shell>
@endsection
