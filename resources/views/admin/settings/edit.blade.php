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
                <small class="ksm-muted">Nel piede e nella descrizione per i motori di ricerca.</small>
            </div>

            {{-- Le righe sotto il logo in testata, solo sul sito principale. --}}
            <div class="ksm-field">
                <label class="ksm-label" for="header_tagline">Sottotitolo sotto il marchio</label>
                <input class="ksm-input" id="header_tagline" name="header_tagline"
                       value="{{ old('header_tagline', $settings->header_tagline) }}"
                       placeholder="{{ trim(__('site.claim_line1').' '.__('site.claim_line2')) }}">
                <small class="ksm-muted">La prima riga sotto il logo, in testata. Vuoto: il testo grigio d'esempio.</small>
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="header_subline">Motto sotto il marchio</label>
                <input class="ksm-input" id="header_subline" name="header_subline"
                       value="{{ old('header_subline', $settings->header_subline) }}"
                       placeholder="{{ __('site.claim_tagline') }}">
                <small class="ksm-muted">La riga in maiuscoletto sotto il sottotitolo. Separa le parole con · e diventano MARE • STORIA • PERSONE.</small>
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
                @if ($settings->site_logo)
                    <img class="ksm-image-current" src="{{ asset('storage/'.$settings->site_logo) }}" alt="">
                @endif
                @include('partials.image-editor-assets')
                <input class="ksm-input" id="site_logo" name="site_logo" type="file" accept="image/jpeg,image/png,image/webp"
                       data-image-editor data-ratios="free,3:1,1:1" data-max="1000x500">
                @error('site_logo')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="favicon">Icona del sito (favicon)</label>
                @if ($settings->favicon)
                    <img class="ksm-image-current" src="{{ asset('storage/'.$settings->favicon) }}" alt="" width="48" height="48">
                @endif
                <input class="ksm-input" id="favicon" name="favicon" type="file" accept="image/jpeg,image/png,image/webp"
                       data-image-editor data-ratios="1:1" data-max="256x256">
                <small class="ksm-muted">Quadrata: si salva in PNG da 256 px.</small>
                @error('favicon')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="hero_image">Immagine di apertura della home (hero)</label>
                @if ($settings->hero_image)
                    <img class="ksm-image-current" src="{{ asset('storage/'.$settings->hero_image) }}" alt="">
                    <label style="display: flex; gap: 6px; align-items: center;"><input type="checkbox" name="remove_hero_image" value="1"> Togli l'immagine</label>
                @endif
                <input class="ksm-input" id="hero_image" name="hero_image" type="file" accept="image/jpeg,image/png,image/webp"
                       data-image-editor data-ratios="2:1,free" data-max="2000x1000">
                <small class="ksm-muted">Sul lato destro dell'apertura, in 2:1. Il soggetto a destra: sugli schermi piccoli si rifila il lato sinistro, già sfumato.</small>
                @error('hero_image')<span class="ksm-error">{{ $message }}</span>@enderror
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
                    Le credenziali qui sotto sono quelle live: in modalità test non vengono lette.
                </p>

                {{-- Interruttore generale: spento, nessun metodo viene offerto. --}}
                <label class="ksm-label" style="display: flex; gap: 8px; align-items: center;">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked($payments->is_active)>
                    Incassi della piattaforma attivi
                </label>

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

                {{-- Un metodo si offre solo con la coppia di credenziali al completo:
                     senza, la pagina del pagamento resta senza scelte. --}}
                <div class="ksm-field" style="margin-top: 14px;">
                    <label class="ksm-label" for="stripe_live_public_key">Chiave pubblica Stripe (live)</label>
                    <input class="ksm-input" id="stripe_live_public_key" name="stripe_live_public_key"
                           value="{{ old('stripe_live_public_key', $payments->stripe_live_public_key) }}">
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="stripe_live_secret_key">Chiave segreta Stripe (live)</label>
                    <input class="ksm-input" id="stripe_live_secret_key" name="stripe_live_secret_key" type="password"
                           placeholder="{{ $payments->stripe_live_secret_key ? 'già impostata' : '' }}">
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="paypal_live_client_id">Client id PayPal (live)</label>
                    <input class="ksm-input" id="paypal_live_client_id" name="paypal_live_client_id"
                           value="{{ old('paypal_live_client_id', $payments->paypal_live_client_id) }}">
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="paypal_live_secret">Segreto PayPal (live)</label>
                    <input class="ksm-input" id="paypal_live_secret" name="paypal_live_secret" type="password"
                           placeholder="{{ $payments->paypal_live_secret ? 'già impostato' : '' }}">
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="kmoney_live_account">Conto KMoney della piattaforma (live)</label>
                    <input class="ksm-input" id="kmoney_live_account" name="kmoney_live_account"
                           value="{{ old('kmoney_live_account', $payments->kmoney_live_account) }}">
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
