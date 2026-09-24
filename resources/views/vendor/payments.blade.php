@extends('layouts.panel')

@section('title', 'Incassi · KSM')
@section('role', 'Area azienda')
@section('nav')@include('vendor.nav')@endsection

@section('content')
    <div class="ksm-panel__head"><h1>Incassi</h1></div>

    {{-- Collegamento KMoney con il numero di conto: token e segreto arrivano da soli dopo l'approvazione. --}}
    <section class="ksm-card" style="padding: 24px; max-width: 680px; margin-bottom: 22px;">
        <h2 style="font-size: 1.05rem; margin-top: 0;">Collega KMoney</h2>
        <p class="ksm-muted" style="font-size: .88rem;">
            Scrivi il numero del tuo conto KMoney: KMoney riceve la richiesta e, appena la approva,
            il collegamento si completa da solo. Non serve copiare token.
        </p>

        @if ($settings->kmoney_pairing_status)
            <p class="ksm-alert {{ $settings->kmoney_pairing_status === 'approved' ? 'ksm-alert--success' : ($settings->kmoney_pairing_status === 'pending' ? '' : 'ksm-alert--error') }}">
                {{ \App\Payments\KMoney\KMoneyPairing::describe($settings->kmoney_pairing_status) }}
                Conto {{ $settings->kmoney_account_number }}{{ $settings->kmoney_pairing_requested_at ? ', richiesto il '.$settings->kmoney_pairing_requested_at->format('d/m/Y H:i') : '' }}.
            </p>
        @endif

        @if ($settings->kmoney_pairing_status === 'pending')
            <form method="POST" action="{{ route('vendor.payments.kmoney.check') }}">
                @csrf @method('PATCH')
                <button class="ksm-btn ksm-btn--ghost" type="submit">Controlla ora</button>
                <small class="ksm-muted">Il controllo avviene anche da solo ogni pochi minuti.</small>
            </form>
        @else
            <form method="POST" action="{{ route('vendor.payments.kmoney.pair') }}" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                @csrf
                <div class="ksm-field" style="margin: 0; flex: 1 1 240px;">
                    <label class="ksm-label" for="kmoney_account_number">Numero di conto KMoney</label>
                    <input class="ksm-input" id="kmoney_account_number" name="kmoney_account_number" autocomplete="off"
                           placeholder="KYB..." value="{{ old('kmoney_account_number', $settings->kmoney_account_number) }}">
                    @error('kmoney_account_number')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
                <button class="ksm-btn ksm-btn--primary" type="submit">
                    {{ $settings->kmoney_pairing_status === 'approved' ? 'Collega di nuovo' : 'Chiedi il collegamento' }}
                </button>
            </form>
        @endif
    </section>

    <form class="ksm-card" style="padding: 24px; max-width: 680px;" method="POST" action="{{ route('vendor.payments.update') }}">
        @csrf @method('PUT')

        <p class="ksm-muted" style="font-size: .88rem;">
            Le chiavi restano salvate: i campi vuoti non sovrascrivono quelle già inserite.
        </p>

        <div class="ksm-field">
            <label class="ksm-label" for="mode">Modalità</label>
            <select class="ksm-select" id="mode" name="mode">
                <option value="test" @selected($settings->mode === 'test')>Test</option>
                <option value="live" @selected($settings->mode === 'live')>Live</option>
            </select>
        </div>

        @foreach (['stripe' => 'Stripe', 'paypal' => 'PayPal', 'kmoney' => 'KMoney'] as $gateway => $label)
            <label class="ksm-label" style="display: flex; gap: 8px; align-items: center;">
                <input type="checkbox" name="enable_{{ $gateway }}" value="1" @checked($settings->{'enable_'.$gateway})>
                {{ $label }}
            </label>
        @endforeach

        <div class="ksm-field" style="margin-top: 16px;">
            <label class="ksm-label" for="stripe_live_public_key">Chiave pubblica Stripe (live)</label>
            <input class="ksm-input" id="stripe_live_public_key" name="stripe_live_public_key"
                   value="{{ old('stripe_live_public_key', $settings->stripe_live_public_key) }}">
        </div>

        <div class="ksm-field">
            <label class="ksm-label" for="stripe_live_secret_key">Chiave segreta Stripe (live)</label>
            <input class="ksm-input" id="stripe_live_secret_key" name="stripe_live_secret_key" type="password"
                   placeholder="{{ $settings->stripe_live_secret_key ? 'già impostata' : '' }}">
        </div>

        <div class="ksm-field">
            <label class="ksm-label" for="paypal_live_client_id">Client id PayPal (live)</label>
            <input class="ksm-input" id="paypal_live_client_id" name="paypal_live_client_id"
                   value="{{ old('paypal_live_client_id', $settings->paypal_live_client_id) }}">
        </div>

        <div class="ksm-field">
            <label class="ksm-label" for="paypal_live_secret">Segreto PayPal (live)</label>
            <input class="ksm-input" id="paypal_live_secret" name="paypal_live_secret" type="password"
                   placeholder="{{ $settings->paypal_live_secret ? 'già impostato' : '' }}">
        </div>

        {{-- KMoney: il cliente paga sul sito KMoney, qui non passano le sue credenziali. --}}
        <h2 style="font-size: 1.05rem; margin-top: 26px;">KMoney</h2>
        <p class="ksm-muted" style="font-size: .88rem;">
            Solo se non usi il collegamento qui sopra: il token si crea sul portale KMoney, in Token API, con il permesso di scrittura.
            Le quote dei prodotti si decidono da <a href="{{ route('vendor.kmoney.edit') }}">KMoney</a>.
        </p>

        <div class="ksm-field">
            <label class="ksm-label" for="kmoney_api_token">Token API KMoney</label>
            <input class="ksm-input" id="kmoney_api_token" name="kmoney_api_token" type="password" autocomplete="off"
                   placeholder="{{ $settings->kmoney_api_token ? 'già impostato' : 'km_...' }}">
        </div>

        <h2 style="font-size: 1.05rem; margin-top: 26px;">Notifiche dal gestore</h2>
        <p class="ksm-muted" style="font-size: .88rem;">
            Servono a registrare l'ordine anche quando il cliente chiude il browser
            prima di tornare sul sito. Incolla questi indirizzi nel pannello del
            gestore, poi riporta qui sotto il dato che ti restituisce.
        </p>

        <div class="ksm-field">
            <label class="ksm-label">Indirizzo per Stripe</label>
            <input class="ksm-input" value="{{ $webhooks['stripe'] }}" readonly onclick="this.select()">
            <small class="ksm-muted">Evento da attivare: checkout.session.completed</small>
        </div>

        <div class="ksm-field">
            <label class="ksm-label" for="stripe_webhook_secret">Segreto del webhook Stripe</label>
            <input class="ksm-input" id="stripe_webhook_secret" name="stripe_webhook_secret" type="password"
                   placeholder="{{ $settings->stripe_webhook_secret ? 'già impostato' : 'whsec_...' }}">
        </div>

        <div class="ksm-field">
            <label class="ksm-label">Indirizzo per PayPal</label>
            <input class="ksm-input" value="{{ $webhooks['paypal'] }}" readonly onclick="this.select()">
            <small class="ksm-muted">Eventi da attivare: CHECKOUT.ORDER.APPROVED e PAYMENT.CAPTURE.COMPLETED</small>
        </div>

        <div class="ksm-field">
            <label class="ksm-label" for="paypal_webhook_id">Identificativo del webhook PayPal</label>
            <input class="ksm-input" id="paypal_webhook_id" name="paypal_webhook_id"
                   value="{{ old('paypal_webhook_id', $settings->paypal_webhook_id) }}">
        </div>

        <div class="ksm-field">
            <label class="ksm-label">Indirizzo per KMoney</label>
            <input class="ksm-input" value="{{ $webhooks['kmoney'] }}" readonly onclick="this.select()">
            <small class="ksm-muted">Sul portale KMoney: Impostazioni, Webhook, evento payment_request.paid</small>
        </div>

        <div class="ksm-field">
            <label class="ksm-label" for="kmoney_webhook_secret">Secret del webhook KMoney</label>
            <input class="ksm-input" id="kmoney_webhook_secret" name="kmoney_webhook_secret" type="password" autocomplete="off"
                   placeholder="{{ $settings->kmoney_webhook_secret ? 'già impostato' : 'mostrato una sola volta alla creazione' }}">
        </div>

        <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
    </form>
@endsection
