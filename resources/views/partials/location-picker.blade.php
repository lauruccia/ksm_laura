{{--
    Posizione dell'azienda sulla mappa. Serve $company.
    Il segnaposto si mette cercando l'indirizzo, cliccando o trascinando; senza
    script restano la casella e la ricerca fatta dal server al salvataggio.
--}}
<div class="ksm-field" data-location-picker
     data-tiles="{{ config('ksm.maps.tiles') }}"
     data-attribution="{{ config('ksm.maps.attribution') }}"
     data-geocoder="{{ config('ksm.maps.geocoder') }}">
    <label class="ksm-label" for="company_location">Posizione sulla mappa</label>

    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <input class="ksm-input" id="company_location" name="company_location" data-location-query
               style="flex: 1; min-width: 220px;" placeholder="Via Armando Veneziani 7, Pomezia"
               value="{{ old('company_location', $company->company_location) }}">
        <button class="ksm-btn ksm-btn--ghost" type="button" data-location-search>Cerca sulla mappa</button>
    </div>

    <small class="ksm-muted" data-location-status aria-live="polite">
        Se il segnaposto non è nel punto giusto, trascinalo o clicca sulla mappa.
        Salvando senza segnaposto, la posizione si cerca dall'indirizzo.
    </small>

    <div class="ksm-location-map" data-location-map></div>

    <input type="hidden" name="latitude" value="{{ old('latitude', $company->latitude) }}" data-location-lat>
    <input type="hidden" name="longitude" value="{{ old('longitude', $company->longitude) }}" data-location-lng>

    @error('company_location')<span class="ksm-error">{{ $message }}</span>@enderror
    @error('latitude')<span class="ksm-error">{{ $message }}</span>@enderror
</div>

@once
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}?v=1.9.4">
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}?v=1.9.4" defer></script>
    <script src="{{ asset('js/maps.js') }}?v={{ filemtime(public_path('js/maps.js')) }}" defer></script>
@endonce
