@extends('layouts.panel')

@section('title', 'Profilo azienda · KSM')
@section('role', 'Area azienda')
@section('nav')@include('vendor.nav')@endsection

@section('content')
    <div class="ksm-panel__head"><h1>Profilo azienda</h1></div>

    <form class="ksm-card" style="padding: 24px; max-width: 780px;" method="POST"
          action="{{ route('vendor.profile.update') }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div class="ksm-field">
            <label class="ksm-label" for="name">Nome</label>
            <input class="ksm-input" id="name" name="name" value="{{ old('name', $company->name) }}" required>
        </div>

        <div class="ksm-field">
            <label class="ksm-label" for="category_id">Categoria</label>
            <select class="ksm-select" id="category_id" name="category_id">
                <option value="">Nessuna</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id', $company->category_id) == $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>

        @foreach ([
            'address' => 'Indirizzo',
            'city' => 'Città',
        ] as $field => $label)
            <div class="ksm-field">
                <label class="ksm-label" for="{{ $field }}">{{ $label }}</label>
                <input class="ksm-input" id="{{ $field }}" name="{{ $field }}"
                       value="{{ old($field, $company->$field) }}">
            </div>
        @endforeach

        {{-- La regione alimenta il filtro "Tutte le regioni" della ricerca pubblica. --}}
        <div class="ksm-field">
            <label class="ksm-label" for="region">Regione</label>
            <select class="ksm-select" id="region" name="region">
                <option value="">Nessuna</option>
                @foreach (config('ksm.regions') as $region)
                    <option value="{{ $region }}" @selected(old('region', $company->region) === $region)>{{ $region }}</option>
                @endforeach
            </select>
        </div>

        @include('partials.location-picker')

        @foreach ([
            'phone' => 'Telefono',
            'email' => 'Email',
            'website' => 'Sito web',
        ] as $field => $label)
            <div class="ksm-field">
                <label class="ksm-label" for="{{ $field }}">{{ $label }}</label>
                <input class="ksm-input" id="{{ $field }}" name="{{ $field }}"
                       value="{{ old($field, $company->$field) }}">
            </div>
        @endforeach

        <div class="ksm-field">
            <label class="ksm-label" for="company_description">Descrizione</label>
            <textarea class="ksm-textarea" id="company_description" name="company_description" data-richtext
                      rows="6">{{ old('company_description', $company->company_description) }}</textarea>
            @include('partials.richtext-assets')
            @error('company_description')<span class="ksm-error">{{ $message }}</span>@enderror
        </div>

        <div class="ksm-grid ksm-grid--2">
            <div class="ksm-field">
                <label class="ksm-label" for="base_shipping_rate">Spedizione base</label>
                <input class="ksm-input" id="base_shipping_rate" name="base_shipping_rate" type="number" step="0.01"
                       value="{{ old('base_shipping_rate', $company->base_shipping_rate) }}">
            </div>
            <div class="ksm-field">
                <label class="ksm-label" for="per_kg_rate">Costo al chilo</label>
                <input class="ksm-input" id="per_kg_rate" name="per_kg_rate" type="number" step="0.01"
                       value="{{ old('per_kg_rate', $company->per_kg_rate) }}">
            </div>
        </div>

        <div class="ksm-grid ksm-grid--2">
            <div class="ksm-field">
                <label class="ksm-label" for="logo">Logo</label>
                @if ($company->logo)
                    <img class="ksm-image-current" src="{{ asset('storage/'.$company->logo) }}" alt="">
                @endif
                @include('partials.image-editor-assets')
                <input class="ksm-input" id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp"
                       data-image-editor data-ratios="1:1,free" data-max="800x800">
                <small class="ksm-muted">Con "Mostra tutta l'immagine" il logo non viene tagliato.</small>
                @error('logo')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>
            <div class="ksm-field">
                <label class="ksm-label" for="banner">Immagine di copertina</label>
                @if ($company->banner)
                    <img class="ksm-image-current" src="{{ asset('storage/'.$company->banner) }}" alt="">
                @endif
                <input class="ksm-input" id="banner" name="banner" type="file" accept="image/jpeg,image/png,image/webp"
                       data-image-editor data-ratios="4:1,free" data-max="2000x500">
                <small class="ksm-muted">Striscia 4:1, come in cima alla pagina dell'azienda. Nelle schede della directory si rifila ai lati.</small>
                @error('banner')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>
        </div>

        <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
    </form>
@endsection
