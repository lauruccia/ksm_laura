@extends('layouts.app')

@section('title', $tenant->brandName().' · '.__('site.claim_line2'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/shop.css') }}?v={{ filemtime(public_path('css/shop.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/home.css') }}?v={{ filemtime(public_path('css/home.css')) }}">
@endpush

@section('content')
    <div class="ksm-home">
    @php
        // Su un dominio della rete testi e immagine dell'apertura vengono dal dominio, se li ha.
        $site = $tenant->content();
        // Sul sito principale la foto caricata nelle Impostazioni, poi public/img/hero.jpg.
        $heroPath = $site->get('hero.image') ?? ($tenant->isNetworkSite() ? null : \App\Models\AdminSetting::current()->hero_image);
        $heroImage = $heroPath ? asset('storage/'.$heroPath) : (file_exists(public_path('img/hero.jpg')) ? asset('img/hero.jpg') : null);
    @endphp

    <section class="ksm-hero">
        <div class="ksm-hero__media @if (! $heroImage) ksm-hero__media--empty @endif"
             @if ($heroImage) style="background-image: url('{{ $heroImage }}')" @endif></div>

        <div class="ksm-container ksm-hero__grid">
            <div>
                <h1>
                    {{ $site->get('hero.title', __('site.hero_title_before')) }}
                    <em>{{ $site->get('hero.highlight', __('site.hero_title_highlight')) }}</em>
                </h1>
                <p>{{ $site->get('hero.text', __('site.hero_text')) }}</p>

                <div class="ksm-hero__actions">
                    <a class="ksm-btn ksm-btn--primary" href="{{ route('companies.index') }}">
                        <x-icon name="search" :size="18" />
                        {{ __('site.hero_companies') }}
                        <x-icon name="arrow" :size="18" />
                    </a>
                    <a class="ksm-btn ksm-btn--on-dark" href="{{ route('products.index') }}">
                        <x-icon name="box" :size="18" />
                        {{ __('site.hero_products') }}
                        <x-icon name="arrow" :size="18" />
                    </a>
                </div>
            </div>

            <div></div>
        </div>

        <p class="ksm-hero__script">{{ $site->get('hero.script', $tenant->isNetworkSite() ? '' : __('site.hero_script')) }}</p>
    </section>

    @php
        // I vantaggi scritti per il dominio valgono anche qui; senza, quelli del marketplace.
        $usp = $site->customBenefits() ?? array_map(fn ($icon, $key) => [
            'icon' => $icon, 'title' => __('site.'.$key), 'text' => __('site.'.$key.'_text'),
        ], ['building', 'box', 'chart', 'users'], ['usp_marketplace', 'usp_directory', 'usp_products', 'usp_presence']);
        $showUsp = $site->enabled('benefits');
        // Un dominio legato a un luogo mostra gia' solo quello: il filtro per regione non serve.
        $hasPlace = $tenant->scope()->place() !== null;
    @endphp

    <div class="ksm-container ksm-usp-wrap @unless ($showUsp) ksm-usp-wrap--flat @endunless">
        @if ($showUsp)
            <div class="ksm-usp" style="--usp-count: {{ count($usp) }}">
                @foreach ($usp as $item)
                    <div class="ksm-usp__item">
                        <span class="ksm-usp__icon"><x-icon :name="$item['icon']" :size="32" /></span>
                        <div>
                            <h3>{{ $item['title'] }}</h3>
                            @if ($item['text'])<p>{{ $item['text'] }}</p>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Una sola casella per azienda, prodotto, settore o luogo, piu' il filtro per regione. --}}
        <form class="ksm-searchbar" method="GET" action="{{ route('companies.index') }}" role="search">
            <div class="ksm-searchbar__box">
                <x-icon name="search" />
                <input id="cerca" name="cerca" type="search" value="{{ request('cerca') }}"
                       placeholder="{{ __('site.search_placeholder') }}"
                       aria-label="{{ __('site.search_placeholder') }}">
            </div>

            <button class="ksm-btn ksm-btn--primary ksm-searchbar__submit" type="submit">
                <x-icon name="search" :size="18" />
                {{ __('site.search_submit') }}
            </button>

            @unless ($hasPlace)
                <div class="ksm-searchbar__filter">
                    <x-icon name="pin" :size="18" />
                    <select id="regione" name="regione" aria-label="{{ __('site.search_region') }}">
                        <option value="">{{ __('site.search_all_regions') }}</option>
                        @foreach (config('ksm.regions') as $region)
                            <option value="{{ $region }}" @selected(request('regione') === $region)>{{ $region }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
        </form>
    </div>

    <x-ad-slot placement="home_below_header" class="ksm-container" />

    <section class="ksm-section ksm-section--after-search">
        <div class="ksm-container">
            <div class="ksm-section-head">
                <div>
                    <h2>{{ __('site.featured_companies') }}</h2>
                    <p>{{ __('site.featured_companies_sub', ['brand' => $tenant->brandName()]) }}</p>
                </div>
                <a href="{{ route('companies.index') }}">
                    {{ __('site.all_companies') }}
                    <x-icon name="arrow" :size="17" />
                </a>
            </div>

            @if ($companies->isEmpty())
                <p class="ksm-muted">{{ __('site.no_results') }}</p>
            @else
                <div class="ksm-grid ksm-grid--4 ksm-rail">
                    @foreach ($companies as $company)
                        <x-company-card :company="$company" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <x-ad-slot placement="home_under_featured_companies" class="ksm-container" />

    {{-- Come funziona: parla dei piani KSM, non ha senso sui domini della rete. --}}
    @unless ($tenant->isNetworkSite())
    <section class="ksm-section ksm-section--alt">
        <div class="ksm-container">
            <div class="ksm-section-head">
                <div>
                    <h2>{{ __('site.how_it_works') }}</h2>
                    <p>{{ __('site.how_it_works_sub') }}</p>
                </div>
            </div>

            <div class="ksm-steps">
                @foreach (['plan', 'company', 'sell'] as $index => $step)
                    <div class="ksm-step">
                        <span class="ksm-step__number">{{ $index + 1 }}</span>
                        <div>
                            <h3>{{ __('site.step_'.$step.'_title') }}</h3>
                            <p>{{ __('site.step_'.$step.'_text') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endunless

    <section class="ksm-section">
        <div class="ksm-container">
            <div class="ksm-section-head">
                <div>
                    <h2>{{ __('site.featured_products') }}</h2>
                    <p>{{ __('site.featured_products_sub') }}</p>
                </div>
                <a href="{{ route('products.index') }}">
                    {{ __('site.all_products') }}
                    <x-icon name="arrow" :size="17" />
                </a>
            </div>

            @if ($products->isEmpty())
                <p class="ksm-muted">{{ __('site.no_results') }}</p>
            @else
                <div class="ksm-shop__grid ksm-home__products" data-cols="4">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" :storefront="true" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <x-ad-slot placement="home_above_footer" class="ksm-container" />

    <x-ad-popup />
    </div>
@endsection
