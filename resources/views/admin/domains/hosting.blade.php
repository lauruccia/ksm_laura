@extends('layouts.panel')

@section('title', 'Server e hosting · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-listhead">
        <h1>Server e hosting</h1>
        <span class="ksm-listhead__actions">
            <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.domains.index') }}">Torna ai domini</a>
        </span>
    </div>

    <div class="ksm-hosting">
        <form class="ksm-card ksm-hosting__form" method="POST" action="{{ route('admin.domains.hosting.update') }}">
            @csrf @method('PUT')

            <p class="ksm-muted" style="margin-top: 0;">
                Ora in uso: <strong>{{ $hosting['panel'] }}</strong>, IP <strong>{{ implode(', ', $hosting['ips']) ?: 'non impostato' }}</strong>
                ({{ $hosting['source'] === 'env' ? 'dal file .env del server' : 'da questa pagina' }}).
            </p>

            <div class="ksm-field">
                <label class="ksm-label" for="panel">Dove stanno i domini</label>
                <select class="ksm-select" id="panel" name="panel" data-hosting-panel>
                    @foreach ($panels as $value => $label)
                        <option value="{{ $value }}" @selected(old('panel', $setting->panel) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div data-hosting-for="whm cpanel none">
                <h2>Server</h2>
                <div class="ksm-field">
                    <label class="ksm-label" for="server_ips">IP del server (separati da virgola)</label>
                    <input class="ksm-input" id="server_ips" name="server_ips" value="{{ old('server_ips', $setting->server_ips) }}" placeholder="{{ $env['ips'] }}">
                    <small class="ksm-muted">Il record A che i clienti mettono sul loro dominio. È anche l'IP a cui si collegano i proxy.</small>
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="server_cname">Oppure un nome per il CNAME (facoltativo)</label>
                    <input class="ksm-input" id="server_cname" name="server_cname" value="{{ old('server_cname', $setting->server_cname) }}" placeholder="domini.ksm.it">
                </div>
            </div>

            <div data-hosting-for="whm">
                <h2>WHM del rivenditore</h2>
                <div class="ksm-field">
                    <label class="ksm-label" for="whm_url">Indirizzo della WHM</label>
                    <input class="ksm-input" id="whm_url" name="whm_url" value="{{ old('whm_url', $setting->whm_url) }}" placeholder="https://server.esempio.it:2087">
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="whm_reseller">Utente rivenditore</label>
                    <input class="ksm-input" id="whm_reseller" name="whm_reseller" value="{{ old('whm_reseller', $setting->whm_reseller) }}">
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="whm_token">Token API (WHM → Manage API Tokens)</label>
                    <input class="ksm-input" id="whm_token" name="whm_token" type="password" autocomplete="new-password"
                           placeholder="{{ $setting->whm_token ? 'già impostato: lascia vuoto per tenerlo' : '' }}">
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="whm_account">Account cPanel dell'app</label>
                    <input class="ksm-input" id="whm_account" name="whm_account" value="{{ old('whm_account', $setting->whm_account) }}" placeholder="ksm">
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="whm_proxy_plan">Pacchetto per gli account dei domini</label>
                    <input class="ksm-input" id="whm_proxy_plan" name="whm_proxy_plan" value="{{ old('whm_proxy_plan', $setting->whm_proxy_plan) }}" placeholder="rivenditore_2Mb">
                    <small class="ksm-muted">Vuoto: i domini si parcheggiano sull'account dell'app (massimo 100 nomi con https).</small>
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="whm_proxy_target">Indirizzo dell'app per i proxy</label>
                    <input class="ksm-input" id="whm_proxy_target" name="whm_proxy_target" value="{{ old('whm_proxy_target', $setting->whm_proxy_target) }}" placeholder="{{ config('app.url') }}">
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="whm_contact_email">Email di contatto degli account</label>
                    <input class="ksm-input" id="whm_contact_email" name="whm_contact_email" type="email" value="{{ old('whm_contact_email', $setting->whm_contact_email) }}">
                </div>
            </div>

            <div data-hosting-for="cpanel">
                <h2>Account cPanel</h2>
                <div class="ksm-field">
                    <label class="ksm-label" for="cpanel_url">Indirizzo del cPanel</label>
                    <input class="ksm-input" id="cpanel_url" name="cpanel_url" value="{{ old('cpanel_url', $setting->cpanel_url) }}" placeholder="https://server.esempio.it:2083">
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="cpanel_user">Utente</label>
                    <input class="ksm-input" id="cpanel_user" name="cpanel_user" value="{{ old('cpanel_user', $setting->cpanel_user) }}">
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="cpanel_token">Token API (cPanel → Gestisci token API)</label>
                    <input class="ksm-input" id="cpanel_token" name="cpanel_token" type="password" autocomplete="new-password"
                           placeholder="{{ $setting->cpanel_token ? 'già impostato: lascia vuoto per tenerlo' : '' }}">
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="cpanel_docroot">Cartella dell'app (dalla home)</label>
                    <input class="ksm-input" id="cpanel_docroot" name="cpanel_docroot" value="{{ old('cpanel_docroot', $setting->cpanel_docroot) }}" placeholder="ksm-next/public">
                </div>
            </div>

            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
            </div>
        </form>

        <aside class="ksm-card ksm-hosting__help">
            <h2>Prova la connessione</h2>
            <p class="ksm-muted">Controlla che token, account e pacchetto funzionino. Non cambia niente.</p>
            <form method="POST" action="{{ route('admin.domains.hosting.test') }}">
                @csrf @method('PATCH')
                <button class="ksm-btn ksm-btn--ghost" type="submit">Prova la connessione</button>
            </form>

            <h2>Traslocare su un altro server</h2>
            <ol>
                <li>Prepara il nuovo server: l'app, il database e, su cPanel/WHM, il token API.</li>
                <li>Qui scegli il nuovo pannello, scrivi il nuovo IP e i dati di accesso, poi <strong>Salva</strong> e <strong>Prova la connessione</strong>.</li>
                <li>In <a href="{{ route('admin.domains.index') }}">Domini</a>: <em>Seleziona → Tutti i risultati</em>, azione <strong>Ricollega</strong>. Ogni dominio viene aggiunto al nuovo pannello e verificato, dalla coda (qualche minuto ogni dieci domini).</li>
                <li>Aggiorna il DNS: se i domini usano i nameserver del server, lo fa già il pannello; altrimenti il record A dei clienti va portato al nuovo IP.</li>
                <li>Filtra per <strong>DNS da configurare</strong> e <strong>Certificato in attesa</strong> per vedere chi manca, e ripeti <strong>Verifica</strong> dopo qualche ora.</li>
            </ol>
            <p class="ksm-muted">Su un VPS con Caddy scegli <em>Nessun pannello</em>: i certificati li fa Caddy da solo appena il DNS punta al nuovo IP. Gli account sul vecchio server non si chiudono da qui: si tolgono dal vecchio pannello a trasloco finito.</p>
            <p class="ksm-muted">Domini registrati: {{ number_format($domainCount, 0, ',', '.') }}.</p>
        </aside>
    </div>

    <style>
        .ksm-hosting { display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); gap: 20px; align-items: start; }
        .ksm-hosting__form, .ksm-hosting__help { padding: 22px; }
        .ksm-hosting h2 { font-size: 1.05rem; margin: 18px 0 10px; }
        .ksm-hosting__help h2:first-child { margin-top: 0; }
        .ksm-hosting__help ol { padding-left: 18px; line-height: 1.55; }
        @media (max-width: 960px) { .ksm-hosting { grid-template-columns: 1fr; } }
    </style>
    <script>
        (function () {
            var select = document.querySelector('[data-hosting-panel]');
            var show = function () {
                document.querySelectorAll('[data-hosting-for]').forEach(function (box) {
                    box.hidden = box.dataset.hostingFor.split(' ').indexOf(select.value) === -1;
                });
            };
            select.addEventListener('change', show);
            show();
        })();
    </script>
@endsection
