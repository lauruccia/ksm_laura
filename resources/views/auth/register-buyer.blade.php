@extends('layouts.app')

@section('title', 'Registrazione privati')

@section('content')
    <x-auth.shell title="Registrati come privato" lead="Bastano pochi dati: l'indirizzo di spedizione lo scrivi al primo ordine.">
        <x-slot:aside>
            <x-auth.perks eyebrow="Account privato" title="Compra dalle aziende del territorio" :items="[
                'Carrello e pagamento in pochi passaggi',
                'Storico ordini e stato delle spedizioni',
                'Recensioni su aziende e prodotti',
            ]">
                <p class="ksm-auth__aside-note">
                    Hai un'attività? <a href="{{ route('register.vendor') }}">Registra la tua azienda</a>
                </p>
            </x-auth.perks>
        </x-slot:aside>

        <x-auth.switch active="buyer" />

        <form method="POST" action="{{ route('register.buyer.store') }}">
            @csrf

            <div class="ksm-field">
                <label class="ksm-label" for="name">Nome e cognome</label>
                <input class="ksm-input" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required autofocus>
                @error('name')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-auth__row">
                <div class="ksm-field">
                    <label class="ksm-label" for="email">Email</label>
                    <input class="ksm-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
                    @error('email')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="phone">Telefono <span class="ksm-auth__optional">facoltativo</span></label>
                    <input class="ksm-input" id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel">
                    @error('phone')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <x-auth.password-fields />

            <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">Crea l'account</button>
        </form>

        <p class="ksm-auth__foot">Hai già un account? <a href="{{ route('login') }}">Accedi</a></p>
    </x-auth.shell>
@endsection
