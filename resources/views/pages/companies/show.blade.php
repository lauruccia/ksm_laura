@extends('layouts.app')

@section('title', $company->name)

@php
    use App\Support\PlanCapabilities;

    // La scheda essenziale la vedono tutti; vetrina, prodotti e
    // recensioni solo se il piano dell'azienda li comprende.
    $showBanner = $company->banner && $company->allows(PlanCapabilities::BANNER);
    $showLogo = $company->logo && $company->allows(PlanCapabilities::LOGO);
    $showcase = $company->allows(PlanCapabilities::SHOWCASE);
    $canSell = $company->allows(PlanCapabilities::SHOP);
    $canReview = $company->allows(PlanCapabilities::REVIEWS);

    // Descrizione, orari e galleria seguono le voci del piano, come nel sito originale.
    $showDescription = $company->company_description
        && ($company->allows(PlanCapabilities::DESCRIPTION) || $showcase);
    $hours = $showcase ? \App\Support\WorkingHours::normalize((array) $company->working_hours) : [];
    $gallery = $company->allows(PlanCapabilities::GALLERY)
        ? array_values(array_filter((array) $company->offer_gallery))
        : [];
@endphp

@section('content')
    <section class="ksm-section">
        <div class="ksm-container">
            <div class="ksm-card" style="margin-bottom: 28px;">
                @if ($showBanner || $showLogo)
                    <div class="ksm-company__cover"
                         @if ($showBanner) style="background-image: url('{{ asset('storage/'.$company->banner) }}')" @endif>
                        <span class="ksm-company__logo"
                              @if ($showLogo) style="background-image: url('{{ asset('storage/'.$company->logo) }}')" @endif></span>
                    </div>
                @endif

                <div class="ksm-company__body">
                    <h1 style="font-size: 1.8rem;">{{ $company->name }}</h1>

                    @if ($showcase)
                        <x-rating :value="$company->reviews_avg_rating" />
                    @endif

                    <ul class="ksm-meta">
                        @if ($company->address)<li>{{ $company->address }} {{ $company->city }}</li>@endif
                        @if ($company->phone)<li><a href="tel:{{ $company->phone }}">{{ $company->phone }}</a></li>@endif
                        @if ($company->email)<li><a href="mailto:{{ $company->email }}">{{ $company->email }}</a></li>@endif
                        @if ($company->website)<li><a href="{{ $company->website }}" rel="noopener">{{ $company->website }}</a></li>@endif
                    </ul>

                    @if ($showDescription)
                        <div class="ksm-richtext">{{ \App\Support\RichText::render($company->company_description) }}</div>
                    @endif
                </div>
            </div>

            @if ($hours || $gallery)
                <div class="ksm-company__extras">
                    @if ($hours)
                        <section class="ksm-card ksm-company__hours" aria-labelledby="orari">
                            <h2 id="orari">Orari di apertura</h2>
                            <table>
                                <tbody>
                                @foreach (\App\Support\WorkingHours::DAYS as $day => $label)
                                    <tr>
                                        <th scope="row">{{ $label }}</th>
                                        <td>{{ isset($hours[$day]) ? $hours[$day]['start'].' – '.$hours[$day]['end'] : 'Chiuso' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </section>
                    @endif

                    @if ($gallery)
                        <section class="ksm-card ksm-company__gallery" aria-labelledby="galleria">
                            <h2 id="galleria">Galleria</h2>
                            <ul>
                                @foreach ($gallery as $path)
                                    <li>
                                        <a href="{{ asset('storage/'.$path) }}" target="_blank" rel="noopener">
                                            <img src="{{ asset('storage/'.$path) }}" loading="lazy"
                                                 alt="{{ $company->name }}, foto {{ $loop->iteration }}">
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </div>
            @endif

            {{-- Mappa OpenStreetMap: con le coordinate e la scheda contatti nel piano. --}}
            @if ($company->latitude !== null && $company->longitude !== null && $company->allows(PlanCapabilities::CONTACT_CARD))
                @php($point = (float) $company->latitude.','.(float) $company->longitude)
                <section class="ksm-card ksm-company__map" aria-labelledby="mappa">
                    <h2 id="mappa">Dove siamo</h2>
                    <div class="ksm-company__mapcanvas" data-company-map
                         data-lat="{{ (float) $company->latitude }}" data-lng="{{ (float) $company->longitude }}"
                         data-name="{{ $company->name }}"
                         data-tiles="{{ config('ksm.maps.tiles') }}" data-attribution="{{ config('ksm.maps.attribution') }}"></div>
                    <p>
                        <a href="https://www.openstreetmap.org/?mlat={{ (float) $company->latitude }}&amp;mlon={{ (float) $company->longitude }}#map=17/{{ (float) $company->latitude }}/{{ (float) $company->longitude }}"
                           target="_blank" rel="noopener">Apri in OpenStreetMap</a>
                        ·
                        <a href="https://www.openstreetmap.org/directions?route=%3B{{ urlencode($point) }}"
                           target="_blank" rel="noopener">Indicazioni stradali</a>
                    </p>
                </section>

                @push('styles')
                    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
                @endpush
                @push('scripts')
                    <script src="{{ asset('vendor/leaflet/leaflet.js') }}" defer></script>
                    <script src="{{ asset('js/maps.js') }}?v={{ filemtime(public_path('js/maps.js')) }}" defer></script>
                @endpush
            @endif

            @if ($canSell && $products)
                <div class="ksm-section-head"><h2>{{ __('site.nav_products') }}</h2></div>

                @if ($products->isEmpty())
                    <p class="ksm-muted">{{ __('site.no_results') }}</p>
                @else
                    <div class="ksm-grid ksm-grid--2">
                        @foreach ($products as $product)
                            <x-product-card :product="$product" />
                        @endforeach
                    </div>
                    <div style="margin-top: 28px;">{{ $products->links() }}</div>
                @endif
            @endif

            @if ($canReview)
                <div class="ksm-section-head" style="margin-top: 40px;"><h2>Recensioni</h2></div>

                <div class="ksm-grid ksm-grid--2">
                    <div>
                        @forelse ($reviews as $review)
                            <article class="ksm-card" style="padding: 16px; margin-bottom: 12px;">
                                <strong>{{ $review->name }}</strong>
                                <x-rating :value="$review->rating" />
                                <p style="margin: 8px 0 0;">{{ $review->comment }}</p>
                            </article>
                        @empty
                            <p class="ksm-muted">{{ __('site.no_results') }}</p>
                        @endforelse
                    </div>

                    <form class="ksm-card" style="padding: 18px;" method="POST"
                          action="{{ route('companies.reviews.store', $company->slug) }}">
                        @csrf
                        <div class="ksm-field">
                            <label class="ksm-label" for="name">Nome</label>
                            <input class="ksm-input" id="name" name="name" required value="{{ old('name') }}">
                        </div>
                        <div class="ksm-field">
                            <label class="ksm-label" for="rating">Voto</label>
                            <select class="ksm-select" id="rating" name="rating" required>
                                @for ($i = 5; $i >= 1; $i--)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="ksm-field">
                            <label class="ksm-label" for="comment">Commento</label>
                            <textarea class="ksm-textarea" id="comment" name="comment" rows="4" required>{{ old('comment') }}</textarea>
                        </div>
                        <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">Pubblica</button>
                    </form>
                </div>
            @endif
        </div>
    </section>
@endsection
