@extends('layouts.app')

@section('title', $tenant->brandName().' · '.__('site.claim_line2'))

@section('content')
    @php($heroImage = file_exists(public_path('img/hero.jpg')) ? asset('img/hero.jpg') : null)

    <section class="ksm-hero">
        <div class="ksm-hero__media @if (! $heroImage) ksm-hero__media--empty @endif"
             @if ($heroImage) style="background-image: url('{{ $heroImage }}')" @endif></div>

        <div class="ksm-container ksm-hero__grid">
            <div>
                <h1>
                    {{ __('site.hero_title_before') }}
                    <em>{{ __('site.hero_title_highlight') }}</em>
                </h1>
                <p>{{ __('site.hero_text') }}</p>

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

        <p class="ksm-hero__script">{{ __('site.hero_script') }}</p>
    </section>

    <div class="ksm-container ksm-usp-wrap">
        <div class="ksm-usp">
            @foreach ([
                ['building', 'usp_marketplace'],
                ['box', 'usp_directory'],
                ['chart', 'usp_products'],
                ['users', 'usp_presence'],
            ] as [$icon, $key])
                <div class="ksm-usp__item">
                    <span class="ksm-usp__icon"><x-icon :name="$icon" :size="32" /></span>
                    <div>
                        <h3>{{ __('site.'.$key) }}</h3>
                        <p>{{ __('site.'.$key.'_text') }}</p>
                    </div>
                </div>
            @endforeach
        </div>

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

            <div class="ksm-searchbar__filter">
                <x-icon name="pin" :size="18" />
                <select id="regione" name="regione" aria-label="{{ __('site.search_region') }}">
                    <option value="">{{ __('site.search_all_regions') }}</option>
                    @foreach (config('ksm.regions') as $region)
                        <option value="{{ $region }}" @selected(request('regione') === $region)>{{ $region }}</option>
                    @endforeach
                </select>
            </div>
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
                <div class="ksm-grid ksm-grid--4">
                    @foreach ($companies as $company)
                        <x-company-card :company="$company" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <x-ad-slot placement="home_under_featured_companies" class="ksm-container" />

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
                <div class="ksm-grid ksm-grid--2">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <x-ad-slot placement="home_above_footer" class="ksm-container" />

    <x-ad-popup />
@endsection
