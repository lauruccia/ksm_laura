@extends('layouts.app')

@php
    $brand = $tenant->brandName();
    $heading = $content['heading'];
    $crumbs = array_merge(
        [['name' => __('site.landing_home'), 'url' => route('home')], ['name' => __('site.nav_companies'), 'url' => route('companies.index')]],
        $trail,
        $place ? [['name' => $heading, 'url' => $canonical]] : []
    );
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => collect($crumbs)->values()->map(fn (array $crumb, int $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'],
                ])->all(),
            ],
            [
                '@type' => 'CollectionPage',
                'name' => $heading,
                'url' => $canonical,
                'mainEntity' => [
                    '@type' => 'ItemList',
                    'numberOfItems' => $total,
                    'itemListElement' => $companies->values()->map(fn ($company, int $i) => array_filter([
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'name' => $company->name,
                        'url' => $company->hasPage() ? route('companies.show', $company->slug) : null,
                    ]))->all(),
                ],
            ],
        ],
    ];
@endphp

@section('title', $content['meta_title'])
@section('meta_description', $content['meta_description'])
@section('canonical', $canonical)
@unless ($indexable)
    @section('robots', 'noindex,follow')
@endunless

@push('scripts')
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('content')
    <section class="ksm-section">
        <div class="ksm-container ksm-directory__container">
            <div class="ksm-directory">
                {{-- Stesso menu dell'elenco aziende: si passa da una categoria all'altra senza perderlo. --}}
                <aside class="ksm-dirnav" aria-labelledby="ksm-dirnav-title">
                    <h2 class="ksm-dirnav__title" id="ksm-dirnav-title">{{ __('site.directory_categories') }}</h2>
                    <nav>
                        <a class="ksm-dirnav__item ksm-dirnav__all" href="{{ route('companies.index') }}">{{ __('site.search_all_categories') }}</a>
                        @include('partials.category-nav', ['nodes' => $categoryNav])
                    </nav>
                </aside>

                <div class="ksm-directory__main">
            <nav class="ksm-landing__crumbs" aria-label="breadcrumb">
                @foreach ($crumbs as $crumb)
                    @if (! $loop->last)
                        <a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a> ›
                    @else
                        <span aria-current="page">{{ $crumb['name'] }}</span>
                    @endif
                @endforeach
            </nav>

            <h1 class="ksm-landing__title">{{ $heading }}</h1>

            {{-- Testi scritti in amministrazione (Testi pagine categoria): semplice testo, i paragrafi si separano con una riga vuota. --}}
            @foreach (preg_split('/\R{2,}/', trim($content['intro'])) as $paragraph)
                <p class="ksm-landing__intro">{!! nl2br(e($paragraph)) !!}</p>
            @endforeach

            @if ($place)
                <p><a href="{{ $categoryUrl }}">{{ __('site.landing_all_italy', ['category' => $category->name]) }}</a></p>
            @endif

            @if ($subcategories)
                <h2 class="ksm-landing__subtitle">{{ __('site.landing_subcategories') }}</h2>
                <ul class="ksm-landing__links">
                    @foreach ($subcategories as $sub)
                        <li><a href="{{ $sub['url'] }}">{{ $sub['name'] }} ({{ number_format($sub['count'], 0, ',', '.') }})</a></li>
                    @endforeach
                </ul>
            @endif

            @if ($content['body'] && $companies->currentPage() === 1)
                {{-- Chiuso: le aziende restano subito sotto gli occhi, il testo e' nella pagina e si apre con un clic. --}}
                <details class="ksm-landing__more">
                    <summary>{{ __('site.landing_read_more') }}</summary>
                    <div class="ksm-landing__body">
                        @foreach (preg_split('/\R{2,}/', trim($content['body'])) as $paragraph)
                            <p>{!! nl2br(e($paragraph)) !!}</p>
                        @endforeach
                    </div>
                </details>
            @endif

            @if ($otherCities->isNotEmpty() || $otherRegions->isNotEmpty())
                {{-- In alto e non in fondo: con il caricamento continuo il fondo non si raggiunge mai, e questi link servono a chi cerca e a chi indicizza. --}}
                <details class="ksm-landing__more">
                    <summary>{{ __('site.landing_other_cities') }} · {{ __('site.landing_other_regions') }}</summary>
                @if ($otherCities->isNotEmpty())
                <h2>{{ __('site.landing_other_cities') }}</h2>
                <ul class="ksm-landing__links">
                    @foreach ($otherCities as $city)
                        <li><a href="{{ $city['url'] }}">{{ $city['name'] }}</a></li>
                    @endforeach
                </ul>
                @endif

                @if ($otherRegions->isNotEmpty())
                <h2>{{ __('site.landing_other_regions') }}</h2>
                <ul class="ksm-landing__links">
                    @foreach ($otherRegions as $region)
                        <li><a href="{{ $region['url'] }}">{{ $region['name'] }}</a></li>
                    @endforeach
                </ul>
                @endif
                </details>
            @endif

            <div class="ksm-grid ksm-directory__grid" data-directory-grid>
                @include('pages.companies.partials.cards')
            </div>

            @if ($companies->hasPages())
                {{-- Senza JavaScript, o se una richiesta fallisce, resta la paginazione. --}}
                <div class="ksm-directory__more" data-directory-more>
                    <span class="ksm-directory__status" role="status">
                        <span class="ksm-directory__spinner" aria-hidden="true"></span>
                        {{ __('site.directory_loading') }}
                    </span>
                    <button class="ksm-btn ksm-btn--primary ksm-directory__retry" type="button" data-directory-retry>
                        {{ __('site.directory_retry') }}
                    </button>
                    <div data-directory-pager>{{ $companies->links() }}</div>
                </div>
            @endif

                </div>
            </div>
        </div>
    </section>
@endsection
