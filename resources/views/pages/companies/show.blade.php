@extends('layouts.app')

@php
    use App\Support\CategoryIcon;
    use App\Support\PlanCapabilities;
    use App\Support\RichText;
    use App\Support\WorkingHours;

    // La pagina esiste solo con la vetrina completa nel piano; qui dentro
    // ogni sezione segue comunque la voce che la concede, come nel sito originale.
    $showBanner = $company->banner && $company->allows(PlanCapabilities::BANNER);
    $showLogo = $company->logo && $company->allows(PlanCapabilities::LOGO);
    $showcase = $company->allows(PlanCapabilities::SHOWCASE);
    $showContacts = $company->allows(PlanCapabilities::CONTACT_CARD);
    $canSell = $company->allows(PlanCapabilities::SHOP);
    $canReview = $company->allows(PlanCapabilities::REVIEWS);

    $showDescription = $company->company_description
        && ($company->allows(PlanCapabilities::DESCRIPTION) || $showcase);
    $hours = $showcase ? WorkingHours::normalize((array) $company->working_hours) : [];
    $status = WorkingHours::status($hours);
    $gallery = $company->allows(PlanCapabilities::GALLERY)
        ? array_values(array_filter((array) $company->offer_gallery))
        : [];

    // Nei dati importati i campi vuoti a volte valgono "-": si trattano come assenti.
    $filled = fn ($value) => trim((string) $value, " \t-–—./") === '' ? null : trim((string) $value);

    $address = $showContacts ? $filled($company->address) : null;
    $phone = $showContacts ? $filled($company->phone) : null;
    $email = $showContacts ? $filled($company->email) : null;
    $website = $showContacts ? $filled($company->website) : null;
    $websiteUrl = $website && ! preg_match('#^https?://#i', $website) ? 'https://'.$website : $website;
    $place = collect([$filled($company->city), $filled($company->region)])->filter()->unique()->implode(', ');

    $hasMap = $company->latitude !== null && $company->longitude !== null && $showContacts;
    $point = $hasMap ? (float) $company->latitude.','.(float) $company->longitude : null;

    $reviewsCount = (int) ($company->reviews_count ?? 0);
    $rating = round((float) $company->reviews_avg_rating, 1);

    // Il modulo scrive all'indirizzo dell'azienda: senza, non ha a chi scrivere.
    $canWrite = (bool) $email;

    // Le voci del menu interno: solo le sezioni che esistono davvero.
    $sections = array_filter([
        'chi-siamo' => $showDescription ? 'Chi siamo' : null,
        'prodotti' => $canSell && $products && $products->total() ? __('site.nav_products') : null,
        'galleria' => $gallery ? 'Galleria' : null,
        'orari' => $hours ? 'Orari' : null,
        'dove-siamo' => $hasMap ? 'Dove siamo' : null,
        'recensioni' => $canReview ? 'Recensioni' : null,
        'contatti' => $canWrite ? 'Contatti' : null,
    ]);

    // Scheda per i motori di ricerca: gli stessi dati che si leggono in pagina.
    $schema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => $company->name,
        'url' => route('companies.show', $company->slug),
        'image' => $showLogo ? asset('storage/'.$company->logo) : null,
        'telephone' => $phone,
        'email' => $email,
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $address,
            'addressLocality' => $filled($company->city),
            'addressRegion' => $filled($company->region),
            'addressCountry' => 'IT',
        ]),
        'geo' => $hasMap ? [
            '@type' => 'GeoCoordinates',
            'latitude' => (float) $company->latitude,
            'longitude' => (float) $company->longitude,
        ] : null,
        'openingHoursSpecification' => collect($hours)->map(fn ($slot, $day) => [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => "https://schema.org/$day",
            'opens' => $slot['start'],
            'closes' => $slot['end'],
        ])->values()->all() ?: null,
        'aggregateRating' => $canReview && $reviewsCount ? [
            '@type' => 'AggregateRating',
            'ratingValue' => $rating,
            'reviewCount' => $reviewsCount,
        ] : null,
    ]);
@endphp

@section('title', $company->name.' · '.$tenant->brandName())
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $company->company_description) ?: $company->name.($place ? ", $place" : ''), 155))

@push('styles')
    @if ($canSell && $products && $products->total())
        <link rel="stylesheet" href="{{ asset('css/shop.css') }}?v={{ filemtime(public_path('css/shop.css')) }}">
    @endif
    <link rel="stylesheet" href="{{ asset('css/company.css') }}?v={{ filemtime(public_path('css/company.css')) }}">
    @if ($hasMap)
        <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}?v=1.9.4">
    @endif
@endpush

@push('scripts')
    <script src="{{ asset('js/company.js') }}?v={{ filemtime(public_path('js/company.js')) }}" defer></script>
    @if ($hasMap)
        <script src="{{ asset('vendor/leaflet/leaflet.js') }}?v=1.9.4" defer></script>
        <script src="{{ asset('js/maps.js') }}?v={{ filemtime(public_path('js/maps.js')) }}" defer></script>
    @endif
@endpush

@section('content')
    <article class="ksm-mini" data-minisite>
        <header class="ksm-mini__hero @unless ($showBanner) ksm-mini__hero--plain @endunless"
                @if ($showBanner) style="--ksm-hero-image: url('{{ asset('storage/'.$company->banner) }}')" @endif>
            <div class="ksm-container ksm-mini__crumbs">
                <a href="{{ route('companies.index') }}">{{ __('site.nav_companies') }}</a>
                @if ($company->category) · {{ $company->category->name }} @endif
            </div>

            <div class="ksm-container ksm-mini__heroinner">
                @if ($showLogo)
                    <span class="ksm-mini__logo" style="background-image: url('{{ asset('storage/'.\App\Support\Images\ImageStore::thumb($company->logo)) }}')"></span>
                @else
                    <span class="ksm-mini__logo ksm-mini__logo--empty" aria-hidden="true">{{ mb_strtoupper(mb_substr($company->name, 0, 1)) }}</span>
                @endif

                <div class="ksm-mini__ident">
                    <h1 class="ksm-mini__name">{{ $company->name }}</h1>

                    <p class="ksm-mini__sub">
                        @if ($company->category)
                            <span>
                                <x-icon :name="CategoryIcon::for($company->category)" :size="16" />
                                {{ $company->category->name }}
                            </span>
                        @endif
                        @if ($place !== '')
                            <span><x-icon name="pin" :size="16" /> {{ $place }}</span>
                        @endif
                        @if ($canReview)
                            <span>
                                <x-rating :value="$rating" />
                                <span>{{ trans_choice(':count recensione|:count recensioni', $reviewsCount, ['count' => $reviewsCount]) }}</span>
                            </span>
                        @endif
                        @if ($hours)
                            <span class="ksm-mini__open @unless ($status['open']) ksm-mini__open--closed @endunless">
                                {{ $status['open'] ? 'Aperto adesso' : 'Chiuso adesso' }}
                            </span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="ksm-container ksm-mini__actions">
                @if ($canWrite)
                    <a class="ksm-btn ksm-btn--primary" href="#contatti">
                        <x-icon name="mail" :size="18" />
                        Contattaci
                    </a>
                @endif
                @if ($phone)
                    <a class="ksm-btn ksm-btn--ghost" href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">
                        <x-icon name="phone" :size="18" />
                        {{ $phone }}
                    </a>
                @endif
                @if ($websiteUrl)
                    <a class="ksm-btn ksm-btn--ghost" href="{{ $websiteUrl }}" target="_blank" rel="noopener nofollow">
                        <x-icon name="globe" :size="18" />
                        Sito
                    </a>
                @endif
                @if ($hasMap)
                    <a class="ksm-btn ksm-btn--ghost" target="_blank" rel="noopener"
                       href="https://www.openstreetmap.org/directions?route=%3B{{ urlencode($point) }}">
                        <x-icon name="pin" :size="18" />
                        Indicazioni stradali
                    </a>
                @endif
            </div>
        </header>

        @if (count($sections) > 1)
            <nav class="ksm-mini__nav" data-minisite-nav aria-label="Sezioni della pagina">
                <div class="ksm-container">
                    <ul>
                        @foreach ($sections as $id => $label)
                            <li><a href="#{{ $id }}">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </nav>
        @endif

        <div class="ksm-container ksm-mini__layout">
            <div class="ksm-mini__main">
                @if ($showDescription)
                    <section class="ksm-card ksm-mini__block" id="chi-siamo" aria-labelledby="chi-siamo-titolo">
                        <h2 id="chi-siamo-titolo">Chi siamo</h2>
                        <div class="ksm-richtext">{{ RichText::render($company->company_description) }}</div>
                    </section>
                @endif

                {{-- Senza prodotti la sezione non compare: il minisito non annuncia il vuoto. --}}
                @if ($canSell && $products && $products->total())
                    <section class="ksm-card ksm-mini__block" id="prodotti" aria-labelledby="prodotti-titolo">
                        <h2 id="prodotti-titolo">{{ __('site.nav_products') }}</h2>

                        {{-- Stessa griglia del catalogo: la card ha i suoi stili in shop.css. --}}
                        <div class="ksm-shop__grid" data-cols="3">
                            @foreach ($products as $product)
                                <x-product-card :product="$product" :storefront="true" />
                            @endforeach
                        </div>

                        @if ($products->hasPages())
                            <div style="margin-top: 24px;">{{ $products->links() }}</div>
                        @endif
                    </section>
                @endif

                @if ($gallery)
                    <section class="ksm-card ksm-mini__block" id="galleria" aria-labelledby="galleria-titolo">
                        <h2 id="galleria-titolo">Galleria</h2>
                        <ul class="ksm-mini__gallery" data-gallery>
                            @foreach ($gallery as $path)
                                <li>
                                    <a href="{{ asset('storage/'.$path) }}" target="_blank" rel="noopener">
                                        <img src="{{ asset('storage/'.\App\Support\Images\ImageStore::thumb($path)) }}" loading="lazy"
                                             alt="{{ $company->name }}, foto {{ $loop->iteration }}">
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($canReview)
                    <section class="ksm-card ksm-mini__block" id="recensioni" aria-labelledby="recensioni-titolo">
                        <h2 id="recensioni-titolo">Recensioni</h2>

                        <div class="ksm-mini__score">
                            <span class="ksm-mini__scorenum">{{ number_format($rating, 1, ',', '') }}</span>
                            <span>
                                <x-rating :value="$rating" />
                                <span class="ksm-muted">{{ trans_choice('su :count recensione|su :count recensioni', $reviewsCount, ['count' => $reviewsCount]) }}</span>
                            </span>
                        </div>

                        @if ($reviews->isNotEmpty())
                            <div class="ksm-mini__reviews">
                                @foreach ($reviews as $review)
                                    <article class="ksm-mini__review">
                                        <header>
                                            <strong>{{ $review->name }}</strong>
                                            <time datetime="{{ $review->created_at?->toDateString() }}">{{ $review->created_at?->format('d/m/Y') }}</time>
                                        </header>
                                        <x-rating :value="$review->rating" />
                                        <p>{{ $review->comment }}</p>
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <p class="ksm-muted">Nessuna recensione: puoi essere il primo.</p>
                        @endif

                        <form class="ksm-mini__form" method="POST" action="{{ route('companies.reviews.store', $company->slug) }}">
                            @csrf
                            <div class="ksm-field">
                                <label class="ksm-label" for="name">Nome</label>
                                <input class="ksm-input" id="name" name="name" required value="{{ old('name') }}">
                            </div>
                            <div class="ksm-field">
                                <label class="ksm-label" for="rating">Voto</label>
                                <select class="ksm-select" id="rating" name="rating" required>
                                    @for ($i = 5; $i >= 1; $i--)
                                        <option value="{{ $i }}" @selected(old('rating') == $i)>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="ksm-field ksm-field--wide">
                                <label class="ksm-label" for="comment">Commento</label>
                                <textarea class="ksm-textarea" id="comment" name="comment" rows="4" required>{{ old('comment') }}</textarea>
                            </div>
                            <button class="ksm-btn ksm-btn--primary" type="submit">Pubblica</button>
                        </form>
                    </section>
                @endif

                @if ($canWrite)
                    <section class="ksm-card ksm-mini__block" id="contatti" aria-labelledby="contatti-titolo">
                        <h2 id="contatti-titolo">Scrivi a {{ $company->name }}</h2>

                        <form class="ksm-mini__form" method="POST" action="{{ route('companies.contact', $company->slug) }}">
                            @csrf
                            <div class="ksm-field">
                                <label class="ksm-label" for="contatto-nome">Nome</label>
                                <input class="ksm-input" id="contatto-nome" name="name" required value="{{ old('name') }}">
                            </div>
                            <div class="ksm-field">
                                <label class="ksm-label" for="contatto-email">Email</label>
                                <input class="ksm-input" id="contatto-email" name="email" type="email" required value="{{ old('email') }}">
                            </div>
                            <div class="ksm-field">
                                <label class="ksm-label" for="contatto-telefono">Telefono</label>
                                <input class="ksm-input" id="contatto-telefono" name="phone" value="{{ old('phone') }}">
                            </div>
                            <div class="ksm-field">
                                <label class="ksm-label" for="contatto-oggetto">Oggetto</label>
                                <input class="ksm-input" id="contatto-oggetto" name="subject" value="{{ old('subject') }}">
                            </div>
                            <div class="ksm-field ksm-field--wide">
                                <label class="ksm-label" for="contatto-messaggio">Messaggio</label>
                                <textarea class="ksm-textarea" id="contatto-messaggio" name="message" rows="5" required>{{ old('message') }}</textarea>
                            </div>
                            <button class="ksm-btn ksm-btn--primary" type="submit">Invia messaggio</button>
                        </form>
                    </section>
                @endif
            </div>

            <aside class="ksm-mini__side">
                @if ($address || $phone || $email || $websiteUrl)
                    <section class="ksm-card ksm-mini__panel" aria-labelledby="contatti-rapidi">
                        <h2 id="contatti-rapidi">Contatti</h2>
                        <ul class="ksm-mini__contacts">
                            @if ($address)
                                <li>
                                    <x-icon name="pin" :size="17" />
                                    <span>{{ $address }}@if ($place !== '')<br>{{ $place }}@endif</span>
                                </li>
                            @endif
                            @if ($phone)
                                <li>
                                    <x-icon name="phone" :size="17" />
                                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">{{ $phone }}</a>
                                </li>
                            @endif
                            @if ($email)
                                <li>
                                    <x-icon name="mail" :size="17" />
                                    <a href="mailto:{{ $email }}">{{ $email }}</a>
                                </li>
                            @endif
                            @if ($websiteUrl)
                                <li>
                                    <x-icon name="globe" :size="17" />
                                    <a href="{{ $websiteUrl }}" target="_blank" rel="noopener nofollow">{{ rtrim(preg_replace('#^https?://(www\.)?#i', '', $websiteUrl), '/') }}</a>
                                </li>
                            @endif
                        </ul>
                    </section>
                @endif

                @if ($hours)
                    <section class="ksm-card ksm-mini__panel ksm-mini__block" id="orari" aria-labelledby="orari-titolo">
                        <h2 id="orari-titolo">Orari di apertura</h2>
                        <table class="ksm-mini__hours">
                            <tbody>
                            @foreach (WorkingHours::DAYS as $day => $label)
                                <tr @class(['is-today' => $day === $status['day']])>
                                    <th scope="row">{{ $label }}</th>
                                    <td>{{ isset($hours[$day]) ? $hours[$day]['start'].' – '.$hours[$day]['end'] : 'Chiuso' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </section>
                @endif

                {{-- Mappa OpenStreetMap: con le coordinate e la scheda contatti nel piano. --}}
                @if ($hasMap)
                    <section class="ksm-card ksm-mini__panel ksm-mini__block" id="dove-siamo" aria-labelledby="dove-siamo-titolo">
                        <h2 id="dove-siamo-titolo">Dove siamo</h2>
                        <div class="ksm-mini__mapcanvas" data-company-map
                             data-lat="{{ (float) $company->latitude }}" data-lng="{{ (float) $company->longitude }}"
                             data-name="{{ $company->name }}"
                             data-tiles="{{ config('ksm.maps.tiles') }}" data-attribution="{{ config('ksm.maps.attribution') }}"></div>
                        <p class="ksm-mini__maplinks">
                            <a href="https://www.openstreetmap.org/?mlat={{ (float) $company->latitude }}&amp;mlon={{ (float) $company->longitude }}#map=17/{{ (float) $company->latitude }}/{{ (float) $company->longitude }}"
                               target="_blank" rel="noopener">Apri in OpenStreetMap</a>
                            ·
                            <a href="https://www.openstreetmap.org/directions?route=%3B{{ urlencode($point) }}"
                               target="_blank" rel="noopener">Indicazioni stradali</a>
                        </p>
                    </section>
                @endif
            </aside>
        </div>
    </article>

    {{-- JSON_HEX_TAG: un nome che contiene </script> non chiude il blocco. --}}
    <script type="application/ld+json">@json($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)</script>
@endsection
