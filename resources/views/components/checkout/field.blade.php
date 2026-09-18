@props(['name', 'label', 'type' => 'text', 'value' => null, 'bag' => 'default', 'id' => null])

{{-- Campo della cassa con l'etichetta dentro il riquadro, che sale quando si
     scrive. Funziona solo con i CSS: il segnaposto vuoto dice se c'e' testo. --}}
@php
    $id = $id ?? $name;
    $fieldErrors = $errors->getBag($bag);
@endphp

<div @class(['ksm-co-field', 'has-error' => $fieldErrors->has($name), $attributes->get('class')])>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}" placeholder=" "
           {{ $attributes->except('class') }}
           @if ($fieldErrors->has($name)) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
    <label for="{{ $id }}">{{ $label }}</label>
    @if ($fieldErrors->has($name))
        <p class="ksm-co-field__error" id="{{ $id }}-error">{{ $fieldErrors->first($name) }}</p>
    @endif
</div>
