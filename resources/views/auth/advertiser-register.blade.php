@extends('layouts.app')

@section('title', 'Registrazione inserzionisti')

@section('content')
    <section class="ksm-section">
        <div class="ksm-container" style="max-width: 620px;">
            <form class="ksm-card" style="padding: 28px;" method="POST" action="{{ route('advertiser.register.store') }}">
                @csrf
                <h1 style="font-size: 1.6rem;">Pubblicità su KSM</h1>
                <p class="ksm-muted">
                    Crea il tuo accesso da inserzionista. Le campagne le concordi con noi e le attiviamo
                    noi; qui troverai visualizzazioni, clic e scadenze.
                </p>

                @foreach ([
                    'business_name' => ['Azienda o marchio', 'text', 'organization'],
                    'name' => ['Nome e cognome del referente', 'text', 'name'],
                    'email' => ['Email', 'email', 'email'],
                    'phone' => ['Telefono', 'text', 'tel'],
                    'vat_number' => ['Partita IVA', 'text', 'off'],
                ] as $field => [$label, $type, $autocomplete])
                    <div class="ksm-field">
                        <label class="ksm-label" for="{{ $field }}">{{ $label }}</label>
                        <input class="ksm-input" id="{{ $field }}" name="{{ $field }}" type="{{ $type }}"
                               autocomplete="{{ $autocomplete }}" value="{{ old($field) }}"
                               @required(in_array($field, ['business_name', 'name', 'email'], true))>
                        @error($field)<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                @endforeach

                <div class="ksm-grid ksm-grid--2">
                    <div class="ksm-field">
                        <label class="ksm-label" for="password">Password</label>
                        <input class="ksm-input" id="password" name="password" type="password" autocomplete="new-password" required>
                        @error('password')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="password_confirmation">Conferma password</label>
                        <input class="ksm-input" id="password_confirmation" name="password_confirmation" type="password"
                               autocomplete="new-password" required>
                    </div>
                </div>

                <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">Crea l'accesso</button>

                <p class="ksm-muted" style="margin-top: 14px; font-size: .9rem;">
                    Hai già un accesso? <a href="{{ route('login') }}">Accedi</a>
                </p>
            </form>
        </div>
    </section>
@endsection
