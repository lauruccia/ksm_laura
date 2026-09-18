@extends('layouts.app')

@section('title', __('site.sign_up'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
@endpush

@section('content')
    <section class="ksm-section ksm-auth">
        <div class="ksm-container ksm-auth__choose">
            <div class="ksm-auth__choose-head">
                <h1 class="ksm-auth__title">Crea il tuo account</h1>
                <p class="ksm-auth__lead">Scegli il tipo di account: i passaggi sono diversi.</p>
            </div>

            <div class="ksm-auth__choices">
                <a class="ksm-auth__choice" href="{{ route('register.buyer') }}">
                    <span class="ksm-auth__option-icon"><x-icon name="user" :size="26" /></span>
                    <h2>Privato</h2>
                    <p>Per comprare dalle aziende del marketplace.</p>
                    <ul class="ksm-auth__perks ksm-auth__perks--light">
                        <li><x-icon name="check" :size="17" /> <span>Registrazione in un minuto</span></li>
                        <li><x-icon name="check" :size="17" /> <span>Ordini sempre a portata di mano</span></li>
                        <li><x-icon name="check" :size="17" /> <span>Recensioni su aziende e prodotti</span></li>
                    </ul>
                    <span class="ksm-btn ksm-btn--ghost ksm-btn--block">Registrati come privato</span>
                </a>

                <a class="ksm-auth__choice" href="{{ route('register.vendor') }}">
                    <span class="ksm-auth__option-icon"><x-icon name="building" :size="26" /></span>
                    <h2>Azienda</h2>
                    <p>Per portare la tua attività nel marketplace.</p>
                    <ul class="ksm-auth__perks ksm-auth__perks--light">
                        <li><x-icon name="check" :size="17" /> <span>Scheda aziendale nella directory</span></li>
                        <li><x-icon name="check" :size="17" /> <span>Vetrina e vendita dei prodotti</span></li>
                        <li><x-icon name="check" :size="17" /> <span>Piano a scelta, anche gratuito</span></li>
                    </ul>
                    <span class="ksm-btn ksm-btn--primary ksm-btn--block">Registra la tua azienda</span>
                </a>
            </div>

            <p class="ksm-auth__foot">Hai già un account? <a href="{{ route('login') }}">Accedi</a></p>
        </div>
    </section>
@endsection
