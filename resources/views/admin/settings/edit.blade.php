@extends('layouts.panel')

@section('title', 'Impostazioni · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head"><h1>Impostazioni</h1></div>

    <div class="ksm-grid ksm-grid--2">
        <form class="ksm-card" style="padding: 22px;" method="POST" action="{{ route('admin.settings.update') }}"
              enctype="multipart/form-data">
            @csrf @method('PUT')
            <h2 style="font-size: 1.05rem;">Sito</h2>

            @foreach ([
                'website_name' => 'Nome del sito',
                'company_name' => 'Ragione sociale',
                'vat_number' => 'Partita IVA',
                'website_url' => 'Indirizzo del sito',
                'website_email' => 'Email principale',
                'support_email' => 'Email assistenza',
                'contact_number' => 'Telefono',
                'address' => 'Indirizzo della sede',
            ] as $field => $label)
                <div class="ksm-field">
                    <label class="ksm-label" for="{{ $field }}">{{ $label }}</label>
                    <input class="ksm-input" id="{{ $field }}" name="{{ $field }}"
                           value="{{ old($field, $settings->$field) }}">
                </div>
            @endforeach

            <div class="ksm-field">
                <label class="ksm-label" for="about">Descrizione breve</label>
                <textarea class="ksm-textarea" id="about" name="about" rows="3">{{ old('about', $settings->about) }}</textarea>
            </div>

            {{-- Le icone social compaiono nel piede solo per le reti con un indirizzo. --}}
            <div class="ksm-grid ksm-grid--2">
                @foreach (['facebook' => 'Pagina Facebook', 'instagram' => 'Profilo Instagram'] as $network => $label)
                    <div class="ksm-field">
                        <label class="ksm-label" for="social_{{ $network }}">{{ $label }}</label>
                        <input class="ksm-input" id="social_{{ $network }}" name="social_links[{{ $network }}]" type="url"
                               placeholder="https://"
                               value="{{ old('social_links.'.$network, $settings->social_links[$network] ?? '') }}">
                    </div>
                @endforeach
            </div>

            <div class="ksm-grid ksm-grid--2">
                <div class="ksm-field">
                    <label class="ksm-label" for="base_shipping_rate">Spedizione base</label>
                    <input class="ksm-input" id="base_shipping_rate" name="base_shipping_rate" type="number" step="0.01"
                           value="{{ old('base_shipping_rate', $settings->base_shipping_rate) }}">
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="per_kg_rate">Costo al chilo</label>
                    <input class="ksm-input" id="per_kg_rate" name="per_kg_rate" type="number" step="0.01"
                           value="{{ old('per_kg_rate', $settings->per_kg_rate) }}">
                </div>
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="site_logo">Logo</label>
                <input class="ksm-input" id="site_logo" name="site_logo" type="file">
            </div>

            <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
        </form>

        <div>
            <form class="ksm-card" style="padding: 22px; margin-bottom: 20px;" method="POST"
                  action="{{ route('admin.settings.payments') }}">
                @csrf @method('PUT')
                <h2 style="font-size: 1.05rem;">Pagamenti di piattaforma</h2>
                <p class="ksm-muted" style="font-size: .85rem;">
                    I campi lasciati vuoti non sovrascrivono le chiavi già salvate.
                </p>

                <div class="ksm-field">
                    <label class="ksm-label" for="mode">Modalità</label>
                    <select class="ksm-select" id="mode" name="mode">
                        <option value="test" @selected($payments->mode === 'test')>Test</option>
                        <option value="live" @selected($payments->mode === 'live')>Live</option>
                    </select>
                </div>

                @foreach (['stripe' => 'Stripe', 'paypal' => 'PayPal', 'kmoney' => 'KMoney', 'bank_transfer' => 'Bonifico bancario'] as $gateway => $label)
                    <label class="ksm-label" style="display: flex; gap: 8px; align-items: center;">
                        <input type="checkbox" name="enable_{{ $gateway }}" value="1"
                               @checked($payments->{'enable_'.$gateway})>
                        {{ $label }}
                    </label>
                @endforeach

                <label class="ksm-label" style="display: flex; gap: 8px; align-items: center; margin-top: 14px;">
                    <input type="checkbox" name="kmoney_euro_fallback" value="1" @checked($payments->kmoney_euro_fallback)>
                    Chi non ha un conto KMoney può pagare tutto in euro
                </label>
                <small class="ksm-muted">Non vale per i venditori con il conto KMoney in debito: da loro si paga solo in KMoney.</small>

                <div class="ksm-field" style="margin-top: 14px;">
                    <label class="ksm-label" for="stripe_live_secret_key">Chiave segreta Stripe (live)</label>
                    <input class="ksm-input" id="stripe_live_secret_key" name="stripe_live_secret_key" type="password"
                           placeholder="{{ $payments->stripe_live_secret_key ? 'già impostata' : '' }}">
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="paypal_live_secret">Segreto PayPal (live)</label>
                    <input class="ksm-input" id="paypal_live_secret" name="paypal_live_secret" type="password"
                           placeholder="{{ $payments->paypal_live_secret ? 'già impostato' : '' }}">
                </div>

                {{-- Il bonifico non ha credenziali: ha le coordinate che
                     l'azienda copia, e una conferma a mano quando arriva. --}}
                <div class="ksm-field">
                    <label class="ksm-label" for="bank_holder">Intestatario del conto</label>
                    <input class="ksm-input" id="bank_holder" name="bank_holder"
                           value="{{ old('bank_holder', $payments->bank_holder) }}">
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="bank_iban">IBAN</label>
                    <input class="ksm-input" id="bank_iban" name="bank_iban"
                           value="{{ old('bank_iban', $payments->bank_iban) }}">
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="bank_bic">BIC</label>
                    <input class="ksm-input" id="bank_bic" name="bank_bic"
                           value="{{ old('bank_bic', $payments->bank_bic) }}">
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="bank_instructions">Istruzioni per il bonifico</label>
                    <textarea class="ksm-textarea" id="bank_instructions" name="bank_instructions"
                              rows="3">{{ old('bank_instructions', $payments->bank_instructions) }}</textarea>
                </div>

                <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
            </form>

            <form class="ksm-card" style="padding: 22px;" method="POST" action="{{ route('admin.settings.mail') }}">
                @csrf @method('PUT')
                <h2 style="font-size: 1.05rem;">Posta in uscita</h2>

                <div class="ksm-field">
                    <label class="ksm-label" for="mail_mailer">Driver</label>
                    <input class="ksm-input" id="mail_mailer" name="mail_mailer"
                           value="{{ old('mail_mailer', $mail->mail_mailer ?? 'smtp') }}">
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="mail_host">Host</label>
                    <input class="ksm-input" id="mail_host" name="mail_host" value="{{ old('mail_host', $mail->mail_host) }}">
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="mail_port">Porta</label>
                    <input class="ksm-input" id="mail_port" name="mail_port" type="number"
                           value="{{ old('mail_port', $mail->mail_port) }}">
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="mail_username">Utente</label>
                    <input class="ksm-input" id="mail_username" name="mail_username"
                           value="{{ old('mail_username', $mail->mail_username) }}">
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="mail_password">Password</label>
                    <input class="ksm-input" id="mail_password" name="mail_password" type="password"
                           placeholder="{{ $mail->mail_password ? 'già impostata' : '' }}">
                </div>

                <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
            </form>
        </div>
    </div>
@endsection
