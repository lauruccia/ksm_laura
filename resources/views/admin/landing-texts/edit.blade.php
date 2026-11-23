@extends('layouts.panel')

@php
    $title = $isTemplate
        ? 'Modello per tutti i luoghi · '.$category->name
        : ($place ? $category->name.' · '.$place['name'] : $category->name.' · tutta Italia');
    $hidden = ['categoria' => $category->id] + ($placeKey !== '' ? ['luogo' => $placeKey] : []);
    $value = fn (string $field) => old($field, $record->{$field});
@endphp

@section('title', $title.' · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>{{ $title }}</h1>
        <span style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.landing_texts.index') }}">Torna all'elenco</a>
            @unless ($isTemplate && ! $sampleName)
                <a class="ksm-btn ksm-btn--ghost" href="{{ $publicUrl }}" target="_blank" rel="noopener">Vedi sul sito</a>
            @endunless
        </span>
    </div>

    @if ($isTemplate)
        <p class="ksm-muted" style="max-width: 75ch;">
            Questo testo vale per <strong>tutte le città e regioni</strong> di «{{ $category->name }}», a meno che per una pagina non ne scriva uno suo.
            Usa i segnaposto: al posto di <code>{luogo}</code> comparirà la città di ogni pagina{{ $sampleName ? ' (qui, come esempio, «'.$sampleName.'»)' : '' }}.
        </p>
    @endif

    <p style="margin: 0 0 16px;">
        @if ($links['category'])
            <a href="{{ $links['category'] }}">Pagina della categoria</a>
        @endif
        @if ($links['category'] && $links['template']) · @endif
        @if ($links['template'])
            <a href="{{ $links['template'] }}">Modello per tutti i luoghi di «{{ $category->name }}»</a>
        @endif
    </p>

    <form method="POST" action="{{ route('admin.landing_texts.update') }}" id="landing-form">
        @csrf
        @method('PUT')
        @foreach ($hidden as $name => $hiddenValue)
            <input type="hidden" name="{{ $name }}" value="{{ $hiddenValue }}">
        @endforeach

        <section class="ksm-box">
            <h2 style="margin-top: 0;">Come appare su Google</h2>
            <div class="ksm-snippet" aria-live="polite">
                <div class="ksm-snippet__url">{{ parse_url($publicUrl, PHP_URL_HOST) }} › categorie › {{ $category->slug }}@if ($place) › {{ $place['slug'] }}@endif</div>
                <div class="ksm-snippet__title" data-snippet-title></div>
                <div class="ksm-snippet__desc" data-snippet-desc></div>
            </div>
        </section>

        <section class="ksm-box">
            <div class="ksm-chips" role="group" aria-label="Segnaposto">
                <span class="ksm-muted">Inserisci nel testo:</span>
                <button type="button" class="ksm-btn ksm-btn--ghost ksm-btn--sm" data-token="{categoria}">{categoria}</button>
                <button type="button" class="ksm-btn ksm-btn--ghost ksm-btn--sm" data-token="{luogo}">{luogo}</button>
                <button type="button" class="ksm-btn ksm-btn--ghost ksm-btn--sm" data-token="{numero}">{numero} aziende</button>
                <button type="button" class="ksm-btn ksm-btn--ghost ksm-btn--sm" data-token="{sito}">{sito}</button>
            </div>
            <small class="ksm-muted">Dove scrivi un segnaposto, sulla pagina compare il valore vero (per esempio «{{ $placeholders['{categoria}'] }}» e «{{ $placeholders['{luogo}'] }}»).</small>

            <div class="ksm-formgrid" style="margin-top: 16px;">
                <div class="ksm-field ksm-field--wide">
                    <div class="ksm-fieldhead">
                        <label class="ksm-label" for="heading">Titolo della pagina</label>
                        <button type="button" class="ksm-btn ksm-btn--ghost ksm-btn--sm" data-fill="heading">Parti dal testo automatico</button>
                    </div>
                    <input class="ksm-input" id="heading" name="heading" type="text" maxlength="255"
                           value="{{ $value('heading') }}" placeholder="{{ $defaults['heading'] }}"
                           data-field="heading" data-default="{{ $defaults['heading'] }}">
                    @error('heading') <span class="ksm-error">{{ $message }}</span> @enderror
                </div>

                <div class="ksm-field ksm-field--wide">
                    <div class="ksm-fieldhead">
                        <label class="ksm-label" for="meta_title">Titolo per Google</label>
                        <button type="button" class="ksm-btn ksm-btn--ghost ksm-btn--sm" data-fill="meta_title">Parti dal testo automatico</button>
                    </div>
                    <input class="ksm-input" id="meta_title" name="meta_title" type="text" maxlength="255"
                           value="{{ $value('meta_title') }}" placeholder="{{ $defaults['meta_title'] }}"
                           data-field="meta_title" data-default="{{ $defaults['meta_title'] }}">
                    <small class="ksm-counter" data-counter="meta_title" data-min="30" data-max="60"></small>
                    @error('meta_title') <span class="ksm-error">{{ $message }}</span> @enderror
                </div>

                <div class="ksm-field ksm-field--wide">
                    <div class="ksm-fieldhead">
                        <label class="ksm-label" for="meta_description">Descrizione per Google</label>
                        <button type="button" class="ksm-btn ksm-btn--ghost ksm-btn--sm" data-fill="meta_description">Parti dal testo automatico</button>
                    </div>
                    <textarea class="ksm-textarea" id="meta_description" name="meta_description" rows="3" maxlength="500"
                              placeholder="{{ $defaults['meta_description'] }}"
                              data-field="meta_description" data-default="{{ $defaults['meta_description'] }}">{{ $value('meta_description') }}</textarea>
                    <small class="ksm-counter" data-counter="meta_description" data-min="70" data-max="160"></small>
                    @error('meta_description') <span class="ksm-error">{{ $message }}</span> @enderror
                </div>

                <div class="ksm-field ksm-field--wide">
                    <div class="ksm-fieldhead">
                        <label class="ksm-label" for="intro">Testo sopra l'elenco delle aziende</label>
                        <button type="button" class="ksm-btn ksm-btn--ghost ksm-btn--sm" data-fill="intro">Parti dal testo automatico</button>
                    </div>
                    <textarea class="ksm-textarea" id="intro" name="intro" rows="5" maxlength="5000"
                              placeholder="{{ $defaults['intro'] }}"
                              data-field="intro" data-default="{{ $defaults['intro'] }}">{{ $value('intro') }}</textarea>
                    <small class="ksm-muted">Poche righe, che dicono cosa si trova qui. Per iniziare un nuovo paragrafo lascia una riga vuota.</small>
                    @error('intro') <span class="ksm-error">{{ $message }}</span> @enderror
                </div>

                <div class="ksm-field ksm-field--wide">
                    <label class="ksm-label" for="body">Approfondimento sotto l'elenco</label>
                    <textarea class="ksm-textarea" id="body" name="body" rows="12" maxlength="20000"
                              placeholder="Facoltativo. Un testo più lungo (consigli, come scegliere, domande frequenti) che compare dopo le aziende, solo nella prima pagina dell'elenco."
                              data-field="body">{{ $value('body') }}</textarea>
                    <small class="ksm-counter" data-counter="body"></small>
                    @error('body') <span class="ksm-error">{{ $message }}</span> @enderror
                </div>
            </div>
        </section>

        <div class="ksm-savebar">
            @if ($record->exists)
                <button class="ksm-btn ksm-btn--ghost" type="submit" form="landing-delete"
                        onclick="return confirm('Togliere il testo scritto a mano? La pagina userà quello automatico.');">Torna al testo automatico</button>
            @endif
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.landing_texts.index') }}">Annulla</a>
            <button class="ksm-btn ksm-btn--ghost" type="submit" name="vai_avanti" value="1">Salva e passa alla prossima senza testo</button>
            <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
        </div>
    </form>

    @if ($record->exists)
        <form method="POST" action="{{ route('admin.landing_texts.destroy') }}" id="landing-delete">
            @csrf
            @method('DELETE')
            @foreach ($hidden as $name => $hiddenValue)
                <input type="hidden" name="{{ $name }}" value="{{ $hiddenValue }}">
            @endforeach
        </form>
    @endif

    <script>
        (function () {
            var variables = @json($placeholders);
            var form = document.getElementById('landing-form');
            var last = null;

            function fill(text) {
                return Object.keys(variables).reduce(function (result, key) {
                    return result.split(key).join(variables[key]);
                }, text);
            }

            function shown(el) {
                return fill(el.value.trim() !== '' ? el.value : (el.dataset.default || ''));
            }

            function refresh() {
                var title = form.querySelector('[data-field="meta_title"]');
                var desc = form.querySelector('[data-field="meta_description"]');
                document.querySelector('[data-snippet-title]').textContent = shown(title);
                document.querySelector('[data-snippet-desc]').textContent = shown(desc);

                form.querySelectorAll('[data-counter]').forEach(function (counter) {
                    var el = form.querySelector('[data-field="' + counter.dataset.counter + '"]');
                    var length = shown(el).length;
                    var min = parseInt(counter.dataset.min || '0', 10);
                    var max = parseInt(counter.dataset.max || '0', 10);
                    counter.textContent = length + ' caratteri' + (max ? ' · ideale ' + min + '–' + max : '');
                    counter.classList.toggle('is-ok', !max || (length >= min && length <= max));
                    counter.classList.toggle('is-warn', !!max && (length < min || length > max));
                });
            }

            form.querySelectorAll('[data-field]').forEach(function (el) {
                el.addEventListener('focus', function () { last = el; });
                el.addEventListener('input', refresh);
            });

            form.querySelectorAll('[data-token]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var el = last || form.querySelector('[data-field="intro"]');
                    var start = el.selectionStart || 0;
                    var end = el.selectionEnd || 0;
                    el.value = el.value.slice(0, start) + button.dataset.token + el.value.slice(end);
                    el.focus();
                    el.selectionStart = el.selectionEnd = start + button.dataset.token.length;
                    refresh();
                });
            });

            form.querySelectorAll('[data-fill]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var el = form.querySelector('[data-field="' + button.dataset.fill + '"]');
                    el.value = el.dataset.default || '';
                    el.focus();
                    refresh();
                });
            });

            refresh();
        })();
    </script>
@endsection
