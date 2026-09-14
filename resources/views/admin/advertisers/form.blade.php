@extends('layouts.panel')

@section('title', ($advertiser->exists ? $advertiser->name : 'Nuovo inserzionista').' · KSM')
@section('role', 'Amministrazione')
@section('crumb', 'Inserzionisti')
@section('nav')@include('admin.nav')@endsection

@section('content')
    @php
        $kind = old('kind', $advertiser->exists ? ($advertiser->isCompany() ? 'company' : 'external') : 'external');
        $companyLabel = $advertiser->company
            ? implode(' · ', array_filter([$advertiser->company->name, $advertiser->company->city, '#'.$advertiser->company->id]))
            : '';
    @endphp

    <div class="ksm-panel__head">
        <h1>{{ $advertiser->exists ? $advertiser->name : 'Nuovo inserzionista' }}</h1>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            @if ($advertiser->exists)
                <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.advertisements.create', ['inserzionista' => $advertiser->id]) }}">Nuova campagna</a>
            @endif
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.advertisers.index') }}">Torna all'elenco</a>
        </div>
    </div>

    <form method="POST" style="max-width: 860px;"
          action="{{ $advertiser->exists ? route('admin.advertisers.update', $advertiser) : route('admin.advertisers.store') }}">
        @csrf
        @if ($advertiser->exists)
            @method('PUT')
        @endif

        <section class="ksm-box" style="margin-bottom: 22px;">
            <div class="ksm-box__head"><h2>Chi è</h2></div>

            <div style="display: flex; gap: 18px; flex-wrap: wrap; margin-bottom: 14px;">
                <label style="display: flex; gap: 6px; align-items: center;">
                    <input type="radio" name="kind" value="external" @checked($kind === 'external')> Inserzionista esterno
                </label>
                <label style="display: flex; gap: 6px; align-items: center;">
                    <input type="radio" name="kind" value="company" @checked($kind === 'company')> Azienda del sito
                </label>
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="company">Azienda del sito</label>
                <input class="ksm-input" id="company" name="company" list="company-options" autocomplete="off"
                       value="{{ old('company', $companyLabel) }}" placeholder="Nome, oppure #id"
                       data-source="{{ route('admin.advertisers.companies') }}">
                <datalist id="company-options"></datalist>
                <small class="ksm-muted">Solo per le aziende del sito: vedranno le campagne nella loro area.</small>
                @error('company_id')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-formgrid">
                @foreach ([
                    'name' => ['Nome dell inserzionista', 'Per un\'azienda del sito, vuoto: il nome dell\'azienda.'],
                    'contact_name' => ['Referente', null],
                    'email' => ['Email', null],
                    'phone' => ['Telefono', null],
                    'vat_number' => ['Partita IVA', null],
                ] as $field => [$label, $hint])
                    <div class="ksm-field">
                        <label class="ksm-label" for="{{ $field }}">{{ $label }}</label>
                        <input class="ksm-input" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $advertiser->$field) }}">
                        @if ($hint)<small class="ksm-muted">{{ $hint }}</small>@endif
                        @error($field)<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                @endforeach
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="notes">Note</label>
                <textarea class="ksm-textarea" id="notes" name="notes" rows="3">{{ old('notes', $advertiser->notes) }}</textarea>
            </div>

            <label class="ksm-label" style="display: flex; gap: 8px; align-items: center;">
                <input type="hidden" name="is_active" value="0">
                <input name="is_active" type="checkbox" value="1" @checked(old('is_active', $advertiser->is_active))>
                Inserzionista attivo
            </label>
            <small class="ksm-muted">Sospeso, le sue campagne non compaiono e l'area non si apre.</small>
        </section>

        <section class="ksm-box" style="margin-bottom: 22px;">
            <div class="ksm-box__head"><h2>Accesso dell'esterno</h2></div>
            <p class="ksm-muted" style="margin-top: 0;">
                Non serve per un'azienda del sito, che entra con l'accesso della sua azienda.
            </p>

            <div class="ksm-formgrid">
                <div class="ksm-field">
                    <label class="ksm-label" for="login_email">Email di accesso</label>
                    <input class="ksm-input" id="login_email" name="login_email" type="email" autocomplete="off"
                           value="{{ old('login_email', $advertiser->user?->email) }}">
                    @error('login_email')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
                <div></div>
                <div class="ksm-field">
                    <label class="ksm-label" for="password">{{ $advertiser->user ? 'Nuova password' : 'Password' }}</label>
                    <input class="ksm-input" id="password" name="password" type="password" autocomplete="new-password">
                    @if ($advertiser->user)<small class="ksm-muted">Lascia vuoto per mantenere quella attuale.</small>@endif
                    @error('password')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="password_confirmation">Conferma password</label>
                    <input class="ksm-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                </div>
            </div>
        </section>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="ksm-btn ksm-btn--primary" type="submit">{{ $advertiser->exists ? 'Salva le modifiche' : 'Crea inserzionista' }}</button>
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.advertisers.index') }}">Annulla</a>
        </div>
    </form>

    @if ($advertiser->exists)
        <form method="POST" action="{{ route('admin.advertisers.destroy', $advertiser) }}" style="margin-top: 32px;"
              onsubmit="return confirm('Eliminare l inserzionista?');">
            @csrf @method('DELETE')
            <button class="ksm-btn ksm-btn--ghost" type="submit">Elimina inserzionista</button>
        </form>
    @endif

    <script>
        // Propone le aziende mentre si scrive; conta l'id in fondo all'etichetta.
        (() => {
            const input = document.getElementById('company');
            const options = document.getElementById('company-options');
            let timer;

            input.addEventListener('input', () => {
                clearTimeout(timer);
                const term = input.value.trim();

                if (/#\d+$/.test(term) || term.length < 2) {
                    return;
                }

                timer = setTimeout(async () => {
                    const response = await fetch(`${input.dataset.source}?q=${encodeURIComponent(term)}`, {
                        headers: { Accept: 'application/json' },
                    });

                    if (!response.ok) {
                        return;
                    }

                    const companies = await response.json();
                    options.replaceChildren(...companies.map(({ label }) => {
                        const option = document.createElement('option');
                        option.value = label;
                        return option;
                    }));
                }, 250);
            });
        })();
    </script>
@endsection
