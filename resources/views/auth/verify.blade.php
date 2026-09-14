@extends('layouts.app')

@section('title', 'Verifica')

@section('content')
    <section class="ksm-section">
        <div class="ksm-container" style="max-width: 440px;">
            <form class="ksm-card" style="padding: 28px;" method="POST" action="{{ route('verification.verify') }}">
                @csrf
                <h1 style="font-size: 1.4rem;">Verifica l'indirizzo</h1>
                <p class="ksm-muted">Inserisci il codice che hai ricevuto via email.</p>

                <div class="ksm-field">
                    <label class="ksm-label" for="code">Codice</label>
                    <input class="ksm-input" id="code" name="code" required autofocus>
                </div>

                <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">Conferma</button>
            </form>

            <form method="POST" action="{{ route('verification.resend') }}" class="ksm-center" style="margin-top: 14px;">
                @csrf
                <button class="ksm-btn ksm-btn--ghost" type="submit">Invia un nuovo codice</button>
            </form>
        </div>
    </section>
@endsection
