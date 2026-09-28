@props([
    'name',                    // nome del campo senza [], es. "sites"
    'options' => [],           // valore => etichetta
    'selected' => [],          // valori scelti
    'placeholder' => 'Cerca...',
    'id' => null,
])

@php
    $selected = array_map('strval', (array) $selected);
    $id ??= 'ms-'.$name;
@endphp

{{--
    Scelta multipla con ricerca, per elenchi lunghi (80 domini e oltre).
    Senza script resta un elenco di caselle scorrevole: il modulo funziona
    lo stesso. Con lo script (js/multiselect.js) le scelte diventano
    etichette e l'elenco si apre solo cercando o cliccando.
--}}
<div class="ksm-multiselect" data-multiselect id="{{ $id }}">
    <div class="ksm-multiselect__box" data-ms-box hidden>
        <span class="ksm-multiselect__chips" data-ms-chips></span>
        <input class="ksm-multiselect__search" type="search" data-ms-search placeholder="{{ $placeholder }}"
               aria-label="{{ $placeholder }}" aria-controls="{{ $id }}-list" aria-expanded="false" autocomplete="off">
    </div>

    <div class="ksm-multiselect__panel" data-ms-panel id="{{ $id }}-list">
        <div class="ksm-multiselect__tools" data-ms-tools hidden>
            <span class="ksm-muted" data-ms-count></span>
            <button type="button" class="ksm-linkbtn" data-ms-all>Seleziona quelli trovati</button>
            <button type="button" class="ksm-linkbtn" data-ms-none>Nessuno</button>
        </div>
        <div class="ksm-multiselect__list">
            @foreach ($options as $value => $label)
                <label class="ksm-multiselect__option" data-ms-option>
                    <input type="checkbox" name="{{ $name }}[]" value="{{ $value }}" @checked(in_array((string) $value, $selected, true))>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <p class="ksm-muted ksm-multiselect__empty" data-ms-empty hidden>Nessun risultato.</p>
    </div>
</div>
