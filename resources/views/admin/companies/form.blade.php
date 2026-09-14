@extends('layouts.panel')

@section('title', ($company->exists ? $company->name : 'Nuova azienda').' · KSM')
@section('role', 'Amministrazione')
@section('crumb', 'Aziende')
@section('nav')@include('admin.nav')@endsection

@section('content')
    @php
        $hours = (array) $company->working_hours;
        $gallery = (array) $company->offer_gallery;
    @endphp

    <div class="ksm-panel__head">
        <h1>{{ $company->exists ? $company->name : 'Nuova azienda' }}</h1>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            @if ($company->exists)
                <a class="ksm-btn ksm-btn--ghost" target="_blank" rel="noopener"
                   href="{{ route('companies.show', ['company' => $company->slug]) }}">Vedi la scheda pubblica</a>
            @endif
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.companies.index') }}">Torna all'elenco</a>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data" style="max-width: 980px;"
          action="{{ $company->exists ? route('admin.companies.update', $company) : route('admin.companies.store') }}">
        @csrf
        @if ($company->exists)
            @method('PUT')
        @endif

        {{-- Azienda, accesso e piano ------------------------------------ --}}
        <section class="ksm-box" style="margin-bottom: 22px;">
            <div class="ksm-box__head"><h2>Azienda e accesso</h2></div>

            <div class="ksm-formgrid">
                <div class="ksm-field">
                    <label class="ksm-label" for="name">Nome azienda</label>
                    <input class="ksm-input" id="name" name="name" value="{{ old('name', $company->name) }}" required>
                    @error('name')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="login_email">Email di accesso</label>
                    <input class="ksm-input" id="login_email" name="login_email" type="email" required autocomplete="off"
                           value="{{ old('login_email', $company->user?->email) }}">
                    <small class="ksm-muted">Con questa il titolare entra nell'area azienda.</small>
                    @error('login_email')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="password">{{ $company->exists ? 'Nuova password' : 'Password' }}</label>
                    <input class="ksm-input" id="password" name="password" type="password" autocomplete="new-password"
                           @required(! $company->exists)>
                    <small class="ksm-muted">
                        {{ $company->exists ? 'Lascia vuoto per mantenere quella attuale.' : 'Comunicala al titolare di persona, non per email.' }}
                    </small>
                    @error('password')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="password_confirmation">Conferma password</label>
                    <input class="ksm-input" id="password_confirmation" name="password_confirmation" type="password"
                           autocomplete="new-password">
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="plan_id">Piano</label>
                    <select class="ksm-select" id="plan_id" name="plan_id">
                        <option value="">Nessun piano</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}"
                                    @selected((string) old('plan_id', $company->plan_id) === (string) $plan->id)>
                                {{ $plan->name }} · {{ $plan->isLifetime() ? 'non scade' : $plan->duration_days.' giorni' }}
                            </option>
                        @endforeach
                    </select>
                    <small class="ksm-muted">
                        @if ($subscription)
                            In corso dal {{ $subscription->starts_at?->format('d/m/Y') }},
                            {{ $subscription->ends_at ? 'scade il '.$subscription->ends_at->format('d/m/Y') : 'senza scadenza' }}.
                        @endif
                        Cambiandolo, il piano nuovo parte subito e senza incasso.
                    </small>
                    @error('plan_id')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="category_id">Categoria</label>
                    <select class="ksm-select" id="category_id" name="category_id">
                        <option value="">Nessuna</option>
                        @foreach ($categories as $id => $name)
                            <option value="{{ $id }}" @selected((string) old('category_id', $company->category_id) === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        {{-- Contatti e spedizioni -------------------------------------- --}}
        <section class="ksm-box" style="margin-bottom: 22px;">
            <div class="ksm-box__head"><h2>Contatti e spedizioni</h2></div>

            <div class="ksm-formgrid">
                @foreach ([
                    'email' => ['Email pubblica', 'email', null],
                    'phone' => ['Telefono', 'text', null],
                    'website' => ['Sito web', 'text', 'Con https:// davanti.'],
                    'base_shipping_rate' => ['Costo fisso di spedizione', 'number', 'Vuoto: vale il costo del sito.'],
                    'per_kg_rate' => ['Costo per kg', 'number', 'Vuoto: vale il costo del sito.'],
                ] as $field => [$label, $type, $hint])
                    <div class="ksm-field">
                        <label class="ksm-label" for="{{ $field }}">{{ $label }}</label>
                        <input class="ksm-input" id="{{ $field }}" name="{{ $field }}" type="{{ $type }}"
                               @if ($type === 'number') step="0.01" min="0" @endif
                               value="{{ old($field, $company->$field) }}">
                        @if ($hint)<small class="ksm-muted">{{ $hint }}</small>@endif
                        @error($field)<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                @endforeach
            </div>
        </section>

        {{-- KMoney ----------------------------------------------------- --}}
        <section class="ksm-box" style="margin-bottom: 22px;">
            <div class="ksm-box__head"><h2>KMoney</h2></div>

            <div class="ksm-formgrid">
                <div class="ksm-field">
                    @php($contract = old('kmoney_contract_percent', $kmoney?->kmoney_contract_percent))
                    <label class="ksm-label" for="kmoney_contract_percent">Quota del contratto</label>
                    <select class="ksm-select" id="kmoney_contract_percent" name="kmoney_contract_percent">
                        <option value="">Non impostata</option>
                        @foreach ($kmoneySteps as $step)
                            <option value="{{ $step }}" @selected($contract !== null && $contract !== '' && (int) $contract === $step)>{{ $step }}%</option>
                        @endforeach
                    </select>
                    <small class="ksm-muted">Vale per i prodotti senza una quota di categoria o di prodotto.</small>
                    @error('kmoney_contract_percent')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" style="display: flex; gap: 8px; align-items: center; margin-top: 28px;">
                        <input type="hidden" name="kmoney_in_debt" value="0">
                        <input name="kmoney_in_debt" type="checkbox" value="1" @checked(old('kmoney_in_debt', $kmoney?->kmoney_in_debt))>
                        Conto KMoney in debito
                    </label>
                    <small class="ksm-muted">
                        In debito tutti i prodotti si pagano al 100% in KMoney e il venditore non può cambiare le quote.
                    </small>
                </div>
            </div>

            @if ($productCategories->isNotEmpty())
                <div class="ksm-table-wrap">
                    <table class="ksm-table">
                        <thead><tr><th>Categoria dei prodotti</th><th>Quota in KMoney</th></tr></thead>
                        <tbody>
                        @foreach ($productCategories as $category)
                            @php($rule = old("kmoney_rules.$category->id", $kmoneyRules[$category->id] ?? ''))
                            <tr>
                                <td>{{ $category->name }}</td>
                                <td>
                                    <select class="ksm-select" name="kmoney_rules[{{ $category->id }}]"
                                            aria-label="Quota KMoney per {{ $category->name }}">
                                        <option value="">Come da contratto</option>
                                        @foreach ($kmoneySteps as $step)
                                            <option value="{{ $step }}" @selected($rule !== null && $rule !== '' && (int) $rule === $step)>{{ $step }}%</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($company->exists)
                <p style="margin: 12px 0 0;">
                    <a href="{{ route('admin.products.index', ['azienda' => $company->id]) }}">Quote dei singoli prodotti</a>
                </p>
            @endif
        </section>

        {{-- Dominio proprio ------------------------------------------- --}}
        <section class="ksm-box" style="margin-bottom: 22px;">
            <div class="ksm-box__head"><h2>Dominio proprio</h2></div>

            <div class="ksm-field">
                <label class="ksm-label" for="custom_domain">Dominio</label>
                <input class="ksm-input" id="custom_domain" name="custom_domain" placeholder="decinabus.it"
                       value="{{ old('custom_domain', $company->custom_domain) }}">
                <small class="ksm-muted">
                    Il cliente punta il dominio al server con un record DNS; il certificato arriva da solo.
                </small>
                @error('custom_domain')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            @if ($company->exists && $company->custom_domain)
                <p style="margin: 0; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <span class="ksm-badge @unless ($company->isConnected()) ksm-badge--muted @endunless">
                        {{ $company->connectionLabel() }}
                    </span>
                    @if ($company->domain_checked_at)
                        <small class="ksm-muted">Ultima verifica {{ $company->domain_checked_at->format('d/m/Y H:i') }}</small>
                    @endif
                    <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit" form="verify-domain">Verifica ora</button>
                </p>
                @if ($company->domain_error)
                    <small class="ksm-error">{{ $company->domain_error }}</small>
                @endif
            @endif
        </section>

        {{-- Indirizzo e descrizione ------------------------------------ --}}
        <section class="ksm-box" style="margin-bottom: 22px;">
            <div class="ksm-box__head"><h2>Indirizzo e descrizione</h2></div>

            <div class="ksm-formgrid">
                <div class="ksm-field">
                    <label class="ksm-label" for="address">Indirizzo</label>
                    <input class="ksm-input" id="address" name="address" value="{{ old('address', $company->address) }}">
                    @error('address')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="city">Città</label>
                    <input class="ksm-input" id="city" name="city" value="{{ old('city', $company->city) }}">
                    @error('city')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="region">Regione</label>
                    <select class="ksm-select" id="region" name="region">
                        <option value="">Nessuna</option>
                        @foreach (config('ksm.regions') as $region)
                            <option value="{{ $region }}" @selected(old('region', $company->region) === $region)>{{ $region }}</option>
                        @endforeach
                    </select>
                    @error('region')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

            </div>

            @include('partials.location-picker')

            <div class="ksm-field">
                <label class="ksm-label" for="company_description">Descrizione</label>
                <textarea class="ksm-textarea" id="company_description" name="company_description" data-richtext
                          rows="8">{{ old('company_description', $company->company_description) }}</textarea>
                @include('partials.richtext-assets')
                @error('company_description')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>
        </section>

        {{-- Orari ------------------------------------------------------ --}}
        <section class="ksm-box" style="margin-bottom: 22px;">
            <div class="ksm-box__head"><h2>Orari di apertura</h2></div>

            <div class="ksm-hours">
                @foreach ($days as $day => $label)
                    <span>{{ $label }}</span>
                    <input class="ksm-input" type="time" name="working_hours[{{ $day }}][start]"
                           aria-label="{{ $label }}, apertura"
                           value="{{ old("working_hours.$day.start", $hours[$day]['start'] ?? '') }}">
                    <input class="ksm-input" type="time" name="working_hours[{{ $day }}][end]"
                           aria-label="{{ $label }}, chiusura"
                           value="{{ old("working_hours.$day.end", $hours[$day]['end'] ?? '') }}">
                    @error("working_hours.$day.start")
                        <span class="ksm-error" style="grid-column: 1 / -1;">{{ $message }}</span>
                    @enderror
                    @error("working_hours.$day.end")
                        <span class="ksm-error" style="grid-column: 1 / -1;">{{ $message }}</span>
                    @enderror
                @endforeach
            </div>

            <small class="ksm-muted">Lascia vuoti gli orari dei giorni di chiusura.</small>
        </section>

        {{-- Immagini --------------------------------------------------- --}}
        <section class="ksm-box" style="margin-bottom: 22px;">
            <div class="ksm-box__head"><h2>Immagini</h2></div>

            <div class="ksm-field">
                <label class="ksm-label" for="gallery">Galleria</label>

                @if ($gallery)
                    <ul class="ksm-gallery">
                        @foreach ($gallery as $path)
                            <li>
                                <img src="{{ asset('storage/'.$path) }}" alt="" loading="lazy">
                                <label>
                                    <input type="checkbox" name="remove_gallery[]" value="{{ $path }}"
                                           @checked(in_array($path, (array) old('remove_gallery', []), true))>
                                    Rimuovi
                                </label>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <input class="ksm-input" id="gallery" name="gallery[]" type="file" multiple
                       accept="image/jpeg,image/png,image/webp">
                <small class="ksm-muted">
                    Fino a {{ $galleryMax }} foto, 5 MB ciascuna. Ora ce ne sono {{ count($gallery) }}.
                </small>
                @error('gallery')<span class="ksm-error">{{ $message }}</span>@enderror
                @error('gallery.*')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-formgrid">
                <div class="ksm-field">
                    <label class="ksm-label" for="logo">Logo</label>
                    @if ($company->logo)
                        <span class="ksm-thumb ksm-thumb--lg"
                              style="background-image: url('{{ asset('storage/'.$company->logo) }}')"></span>
                    @endif
                    <input class="ksm-input" id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp">
                    <small class="ksm-muted">JPG, PNG o WebP, massimo 2 MB.</small>
                    @error('logo')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="banner">Immagine banner</label>
                    @if ($company->banner)
                        <span class="ksm-thumb ksm-thumb--wide"
                              style="background-image: url('{{ asset('storage/'.$company->banner) }}')"></span>
                    @endif
                    <input class="ksm-input" id="banner" name="banner" type="file" accept="image/jpeg,image/png,image/webp">
                    <small class="ksm-muted">JPG, PNG o WebP, massimo 4 MB.</small>
                    @error('banner')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        {{-- Stato ------------------------------------------------------ --}}
        <section class="ksm-box" style="margin-bottom: 22px;">
            <label class="ksm-label" style="display: flex; gap: 8px; align-items: center;">
                <input type="hidden" name="is_active" value="0">
                <input name="is_active" type="checkbox" value="1" @checked(old('is_active', $company->is_active))>
                Azienda attiva
            </label>
            <small class="ksm-muted">Senza un piano l'azienda non compare nella directory, anche se attiva.</small>
        </section>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="ksm-btn ksm-btn--primary" type="submit">{{ $company->exists ? 'Salva le modifiche' : 'Crea azienda' }}</button>
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.companies.index') }}">Annulla</a>
        </div>
    </form>

    {{-- Il pulsante "Verifica ora" sta dentro il modulo principale, ma invia questo. --}}
    @if ($company->exists && $company->custom_domain)
        <form id="verify-domain" method="POST" action="{{ route('admin.companies.domain', $company) }}">
            @csrf @method('PATCH')
        </form>
    @endif

    @if ($company->exists)
        <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" style="margin-top: 32px;"
              onsubmit="return confirm('Eliminare l azienda insieme a prodotti e ordini? Non si torna indietro.');">
            @csrf @method('DELETE')
            <button class="ksm-btn ksm-btn--ghost" type="submit">Elimina azienda</button>
            <small class="ksm-muted">Cancella anche prodotti e ordini. Per nasconderla basta spegnerla.</small>
        </form>
    @endif
@endsection
