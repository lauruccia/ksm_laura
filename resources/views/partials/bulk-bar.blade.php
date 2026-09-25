{{--
    Barra delle azioni in blocco sopra un elenco.

    Vuole: $action (indirizzo), $paginator, $actions (valore => etichetta),
    $noun e $nounOne (per esempio "prodotti" e "prodotto"). Facoltativi:
    $feminine (true per aziende, pagine: "Nessuna azienda selezionata"),
    $percentSteps (quote KMoney, se c'e' l'azione "kmoney"), $statuses
    (valore => etichetta, se c'e' l'azione "status"), $onlySelected (azioni
    che non valgono su "tutti i risultati", come eliminare). Le caselle delle righe si
    legano con form="bulk" name="ids[]" data-bulk-item; quella in testa
    alla tabella ha data-bulk-page. Vedi public/js/bulk.js.
--}}
<form id="bulk" class="ksm-bulk" method="POST" action="{{ $action }}" data-bulk
      data-total="{{ $paginator->total() }}" data-noun="{{ $noun }}" data-noun-one="{{ $nounOne }}"
      data-only-selected="{{ implode(',', $onlySelected ?? []) }}" data-feminine="{{ ($feminine ?? false) ? 1 : 0 }}">
    @csrf
    @method('PATCH')
    <input type="hidden" name="scope" value="selected" data-bulk-scope>
    {{-- "Tutti i risultati" vale per la stessa ricerca dell'elenco. --}}
    @foreach (request()->except(['page', 'per_pagina']) as $key => $value)
        @if (is_string($value) && filled($value))
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach

    <div class="ksm-bulk__bar">
        <details class="ksm-bulk__menu">
            <summary class="ksm-btn ksm-btn--ghost ksm-btn--sm">
                <x-icon name="check" :size="16" /> Seleziona <x-icon name="chevron-down" :size="14" />
            </summary>
            <div class="ksm-bulk__options">
                <button type="button" data-bulk-select="page">{{ ($feminine ?? false) ? 'Tutte' : 'Tutti' }} in questa pagina ({{ $paginator->count() }})</button>
                @if ($paginator->total() > $paginator->count())
                    <button type="button" data-bulk-select="all">Tutti i risultati ({{ number_format($paginator->total(), 0, ',', '.') }})</button>
                @endif
                <button type="button" data-bulk-select="none">{{ ($feminine ?? false) ? 'Nessuna' : 'Nessuno' }}</button>
            </div>
        </details>

        <span class="ksm-bulk__count" data-bulk-count aria-live="polite">{{ ($feminine ?? false) ? 'Nessuna '.$nounOne.' selezionata' : 'Nessun '.$nounOne.' selezionato' }}</span>

        <span class="ksm-bulk__do">
            <label class="ksm-sr-only" for="bulk_action">Azione</label>
            <select class="ksm-select" id="bulk_action" name="action" data-bulk-action>
                @foreach ($actions as $value => $label)
                    <option value="{{ $value }}" @selected(old('action') === $value)>{{ $label }}</option>
                @endforeach
            </select>

            @isset($actions['kmoney'])
                <label class="ksm-sr-only" for="bulk_percent">Quota KMoney</label>
                <select class="ksm-select" id="bulk_percent" name="percent" data-bulk-percent>
                    <option value="auto">Automatica</option>
                    @foreach ($percentSteps as $step)
                        <option value="{{ $step }}">{{ $step }}%</option>
                    @endforeach
                </select>
            @endisset

            @isset($actions['status'])
                <label class="ksm-sr-only" for="bulk_status">Nuovo stato</label>
                <select class="ksm-select" id="bulk_status" name="status" data-bulk-extra="status">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            @endisset

            <button class="ksm-btn ksm-btn--primary ksm-btn--sm" type="submit" data-bulk-submit>Applica</button>
        </span>
    </div>

    <p class="ksm-bulk__banner" data-bulk-banner hidden>
        <span data-bulk-banner-text></span>
        <button type="button" data-bulk-banner-button></button>
    </p>

    @error('ids')<p class="ksm-bulk__error">{{ $message }}</p>@enderror
    {{-- Dentro il modulo: fuori starebbe fra la barra e la tabella e la staccherebbe. --}}
    <script src="{{ asset('js/bulk.js') }}?v={{ filemtime(public_path('js/bulk.js')) }}" defer></script>
</form>
