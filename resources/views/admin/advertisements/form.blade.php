@extends('layouts.panel')

@section('title', ($campaign->exists ? $campaign->name : 'Nuova campagna').' · KSM')
@section('role', 'Amministrazione')
@section('crumb', 'Campagne banner')
@section('nav')@include('admin.nav')@endsection

@section('content')
    @php
        $selected = fn (string $field, $current) => array_map('strval', (array) old($field, $current ?? []));
        $chosenLocations = $selected('locations', $campaign->locations);
        $chosenDomains = $selected('target_domains', $campaign->target_domains);
        $chosenCategories = $selected('target_categories', $campaign->target_categories);
        $billingNow = old('billing', $campaign->billing ?? 'period');
    @endphp

    <div class="ksm-panel__head">
        <h1>{{ $campaign->exists ? $campaign->name : 'Nuova campagna' }}</h1>
        <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.advertisements.index') }}">Torna alle campagne</a>
    </div>

    <form method="POST" enctype="multipart/form-data" style="max-width: 980px;"
          action="{{ $campaign->exists ? route('admin.advertisements.update', $campaign) : route('admin.advertisements.store') }}">
        @csrf
        @if ($campaign->exists)
            @method('PUT')
        @endif

        <section class="ksm-box" style="margin-bottom: 22px;">
            <div class="ksm-box__head"><h2>Campagna</h2></div>

            <div class="ksm-formgrid">
                <div class="ksm-field">
                    <label class="ksm-label" for="advertiser_id">Inserzionista</label>
                    <select class="ksm-select" id="advertiser_id" name="advertiser_id">
                        <option value="">Nessuno, campagna del sito</option>
                        @foreach ($advertisers as $advertiser)
                            <option value="{{ $advertiser->id }}" @selected((string) old('advertiser_id', $campaign->advertiser_id) === (string) $advertiser->id)>
                                {{ $advertiser->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="ksm-muted"><a href="{{ route('admin.advertisers.create') }}">Nuovo inserzionista</a></small>
                    @error('advertiser_id')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="name">Nome della campagna</label>
                    <input class="ksm-input" id="name" name="name" value="{{ old('name', $campaign->name) }}" required>
                    @error('name')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="link">Collegamento</label>
                    <input class="ksm-input" id="link" name="link" type="url" placeholder="https://"
                           value="{{ old('link', $campaign->link) }}" required>
                    @error('link')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="image">Immagine</label>
                    @if ($campaign->img)
                        <img src="{{ asset('storage/'.$campaign->img) }}" alt=""
                             style="display: block; max-width: 100%; max-height: 90px; margin-bottom: 8px; border-radius: 6px;">
                    @endif
                    <input class="ksm-input" id="image" name="image" type="file"
                           accept="image/jpeg,image/png,image/webp,image/gif" @required(! $campaign->img)>
                    <small class="ksm-muted">JPG, PNG, WebP o GIF, massimo 4 MB.</small>
                    @error('image')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <label class="ksm-label" style="display: flex; gap: 8px; align-items: center;">
                <input type="hidden" name="status" value="0">
                <input name="status" type="checkbox" value="1" @checked(old('status', $campaign->status))>
                Campagna accesa
            </label>
        </section>

        <section class="ksm-box" style="margin-bottom: 22px;">
            <div class="ksm-box__head"><h2>Posizioni</h2></div>

            <div class="ksm-checkgrid">
                @foreach ($placements as $key => $label)
                    <label>
                        <input type="checkbox" name="locations[]" value="{{ $key }}" @checked(in_array($key, $chosenLocations, true))>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            @error('locations')<span class="ksm-error">{{ $message }}</span>@enderror
        </section>

        <section class="ksm-box" style="margin-bottom: 22px;">
            <div class="ksm-box__head"><h2>Come si paga</h2></div>

            <div style="display: flex; gap: 18px; flex-wrap: wrap; margin-bottom: 14px;">
                @foreach ($billing as $key => $label)
                    <label style="display: flex; gap: 6px; align-items: center;">
                        <input type="radio" name="billing" value="{{ $key }}" @checked($billingNow === $key)>
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="ksm-formgrid">
                <div class="ksm-field">
                    <label class="ksm-label" for="starts_at">Inizio</label>
                    <input class="ksm-input" id="starts_at" name="starts_at" type="date"
                           value="{{ old('starts_at', $campaign->starts_at?->format('Y-m-d')) }}">
                    <small class="ksm-muted">Vuoto: parte subito.</small>
                    @error('starts_at')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="ends_at">Fine</label>
                    <input class="ksm-input" id="ends_at" name="ends_at" type="date"
                           value="{{ old('ends_at', $campaign->ends_at?->format('Y-m-d')) }}">
                    <small class="ksm-muted">Obbligatoria a periodo. Il giorno di fine è compreso.</small>
                    @error('ends_at')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="max_impressions">Visualizzazioni acquistate</label>
                    <input class="ksm-input" id="max_impressions" name="max_impressions" type="number" min="1"
                           value="{{ old('max_impressions', $campaign->max_impressions) }}">
                    <small class="ksm-muted">Obbligatorie a visualizzazioni. Raggiunte, la campagna si ferma.</small>
                    @error('max_impressions')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="max_clicks">Clic acquistati</label>
                    <input class="ksm-input" id="max_clicks" name="max_clicks" type="number" min="1"
                           value="{{ old('max_clicks', $campaign->max_clicks) }}">
                    <small class="ksm-muted">Obbligatori a clic. Raggiunti, la campagna si ferma.</small>
                    @error('max_clicks')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <section class="ksm-box" style="margin-bottom: 22px;">
            <div class="ksm-box__head"><h2>Dove compare</h2></div>
            <p class="ksm-muted" style="margin-top: 0;">Lasciato vuoto, un bersaglio vale ovunque.</p>

            <div class="ksm-field">
                <span class="ksm-label">Domini</span>
                <div class="ksm-checkgrid">
                    <label>
                        <input type="checkbox" name="target_domains[]" value="{{ \App\Support\Ads\AdContext::MAIN_SITE }}"
                               @checked(in_array((string) \App\Support\Ads\AdContext::MAIN_SITE, $chosenDomains, true))>
                        Sito principale
                    </label>
                    @foreach ($domains as $domain)
                        <label>
                            <input type="checkbox" name="target_domains[]" value="{{ $domain->id }}"
                                   @checked(in_array((string) $domain->id, $chosenDomains, true))>
                            {{ $domain->domain }}
                        </label>
                    @endforeach
                </div>
                @error('target_domains.*')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="target_cities">Città</label>
                <input class="ksm-input" id="target_cities" name="target_cities" placeholder="Roma, Ostia, Pomezia"
                       value="{{ old('target_cities', implode(', ', (array) $campaign->target_cities)) }}">
                <small class="ksm-muted">Separate da virgola. Valgono la città della pagina o del dominio.</small>
                @error('target_cities')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-field">
                <span class="ksm-label">Categorie di aziende</span>
                <div class="ksm-checkgrid">
                    @foreach ($categories as $category)
                        <label>
                            <input type="checkbox" name="target_categories[]" value="{{ $category->id }}"
                                   @checked(in_array((string) $category->id, $chosenCategories, true))>
                            {{ $category->name }}
                        </label>
                    @endforeach
                </div>
                <small class="ksm-muted">Una categoria madre comprende le sue sottocategorie.</small>
                @error('target_categories.*')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>
        </section>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="ksm-btn ksm-btn--primary" type="submit">{{ $campaign->exists ? 'Salva le modifiche' : 'Crea campagna' }}</button>
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.advertisements.index') }}">Annulla</a>
        </div>
    </form>

    @if ($campaign->exists)
        <form method="POST" action="{{ route('admin.advertisements.destroy', $campaign) }}" style="margin-top: 32px;"
              onsubmit="return confirm('Eliminare la campagna con le sue statistiche?');">
            @csrf @method('DELETE')
            <button class="ksm-btn ksm-btn--ghost" type="submit">Elimina campagna</button>
            <small class="ksm-muted">Per fermarla basta spegnerla: le statistiche restano.</small>
        </form>
    @endif
@endsection
