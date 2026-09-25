@php
    /*
     * Blocchi in cima allo shop dei domini: testi, immagine, vantaggi,
     * categorie e prodotti in evidenza li sceglie il dominio
     * (Amministrazione, Domini); i testi di partenza sono le traduzioni.
     *
     * Sul sito principale non si vedono: li' il catalogo apre da solo, con
     * l'impostazione della directory delle aziende.
     */
    $site = $tenant->content();
    $hero = $site->hero();
    $benefits = $site->benefits();
    $rail = $site->categories();
    $featured = $site->featured();
@endphp

@if ($site->enabled('hero'))
    <section class="ksm-store-hero @if ($hero['has_photo']) ksm-store-hero--photo @endif">
        {{-- Sul sito principale l'immagine e' uguale per tutti: la foto di un prodotto
             metterebbe in vetrina una sola azienda fra tutte quelle del circuito. --}}
        @if ($hero['image'])
            <img class="ksm-store-hero__image" src="{{ $hero['image'] }}" alt="" aria-hidden="true" fetchpriority="high">
        @endif
        <div class="ksm-container">
            <div class="ksm-store-hero__copy">
                <span class="ksm-store-hero__eyebrow">{{ $hero['eyebrow'] }}</span>
                <h1>{{ $hero['title'] }} <em>{{ $hero['highlight'] }}</em></h1>
                <p>{{ $hero['text'] }}</p>
                <div class="ksm-store-hero__actions">
                    <a class="ksm-btn ksm-btn--primary" href="{{ $hero['primary_url'] }}"><x-icon name="cart" :size="19" />{{ $hero['primary_label'] }}<x-icon name="arrow" :size="18" /></a>
                    @if ($hero['secondary_label'])
                        <a class="ksm-btn ksm-store-hero__secondary" href="{{ $hero['secondary_url'] }}">{{ $hero['secondary_label'] }}<x-icon name="arrow" :size="18" /></a>
                    @endif
                </div>
            </div>
        </div>
        @if ($hero['script'])
            <p class="ksm-store-hero__script">{{ $hero['script'] }}</p>
            @include('partials.font-caveat')
        @endif
        @if ($hero['badge_title'] || $hero['badge_text'])
            <div class="ksm-store-hero__badge">
                @if ($hero['badge_flag'])
                    <span class="ksm-store-hero__flag" aria-hidden="true"><i></i><i></i><i></i></span>
                @endif
                <span><strong>{{ $hero['badge_title'] }}</strong>{{ $hero['badge_text'] }}</span>
            </div>
        @endif
    </section>
@endif

<div class="ksm-container">
    @if ($site->enabled('benefits'))
        <div class="ksm-store-benefits @unless ($site->enabled('hero')) ksm-store-benefits--flat @endunless">
            @foreach ($benefits as $benefit)
                <div><x-icon :name="$benefit['icon']" :size="32" /><span><strong>{{ $benefit['title'] }}</strong>@if ($benefit['text'])<small>{{ $benefit['text'] }}</small>@endif</span></div>
            @endforeach
        </div>
    @endif

    @if ($rail['enabled'])
        <nav class="ksm-store-categories" aria-label="{{ __('site.shop_categories') }}">
            <div class="ksm-section-head"><h2>{{ $rail['title'] }}</h2><a href="{{ $shopUrl([], ['categoria']) }}#catalogo">{{ $rail['link_label'] }}<x-icon name="arrow" :size="18" /></a></div>
            <div class="ksm-store-categories__rail">
                @foreach ($railCategories as $category)
                    @continue(! $category->visible_count)
                    @php
                        $categoryImage = $category->image && \Illuminate\Support\Facades\Storage::disk('public')->exists($category->image)
                            ? asset('storage/'.\App\Support\Images\ImageStore::thumb($category->image))
                            : null;
                    @endphp
                    <a class="ksm-store-category @if ($categoryImage) ksm-store-category--image @endif" href="{{ $shopUrl(['categoria' => $category->id]) }}#catalogo" @if ($currentCategory?->id === $category->id) aria-current="page" @endif>
                        @if ($categoryImage)
                            <img class="ksm-store-category__image" src="{{ $categoryImage }}" alt="" loading="lazy">
                        @else
                            <span class="ksm-store-category__icon"><x-icon :name="$category->icon ?: 'box'" :size="34" /></span>
                        @endif
                        <strong>{{ $category->name }}</strong><x-icon name="arrow" :size="16" />
                    </a>
                @endforeach
                @if ($rail['offers'])
                    <a class="ksm-store-category ksm-store-category--offer" href="{{ $shopUrl(['offerta' => 1]) }}#catalogo"><span class="ksm-store-category__icon"><x-icon name="tag" :size="34" /></span><strong>{{ __('site.shop_on_sale') }}</strong><x-icon name="arrow" :size="16" /></a>
                @endif
            </div>
        </nav>
    @endif

    @if ($featured['enabled'] && $featuredProducts->isNotEmpty())
        <section class="ksm-store-featured" aria-labelledby="ksm-featured-title">
            <div class="ksm-section-head ksm-shop__head">
                <div>
                    <h2 id="ksm-featured-title">{{ $featured['title'] }}</h2>
                    @if ($featured['subtitle'])
                        <p>{{ $featured['subtitle'] }}</p>
                    @endif
                </div>
                @if ($featured['search'])
                    <form class="ksm-store-search" role="search" method="GET" action="{{ route('products.index') }}#catalogo">
                        <x-icon name="search" :size="20" />
                        <input class="ksm-input" type="search" name="cerca" value="{{ request('cerca') }}" placeholder="{{ __('storefront.search') }}" aria-label="{{ __('site.shop_search_label') }}">
                        <button class="ksm-btn ksm-btn--primary" type="submit">{{ __('storefront.search_button') }}</button>
                    </form>
                @endif
            </div>
            <div class="ksm-shop__grid ksm-store-featured__grid" data-cols="6">
                @foreach ($featuredProducts as $product)
                    <x-product-card :product="$product" :storefront="true" />
                @endforeach
            </div>
        </section>
    @endif
</div>
