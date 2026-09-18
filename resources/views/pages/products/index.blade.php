@extends('layouts.app')

@section('title', __('site.nav_products').' · '.$tenant->brandName())

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/shop.css') }}?v={{ filemtime(public_path('css/shop.css')) }}">
@endpush

@section('content')
    @php
        // Indirizzo del catalogo con alcuni filtri cambiati o tolti: gli altri restano, la pagina riparte da 1.
        $shopUrl = fn (array $changes = [], array $drop = []) => route('products.index', array_filter(
            array_merge(request()->except(array_merge(['page'], $drop, array_keys($changes))), $changes),
            fn ($value) => $value !== null && $value !== ''
        ));

        $currentBrand = $brands->firstWhere('id', request()->integer('marca'));
        $priceMin = request()->float('prezzo_min');
        $priceMax = request()->float('prezzo_max');

        $activeFilters = array_values(array_filter([
            $currentCategory ? ['label' => $currentCategory->name, 'url' => $shopUrl([], ['categoria'])] : null,
            request()->filled('cerca') ? ['label' => '“'.request('cerca').'”', 'url' => $shopUrl([], ['cerca'])] : null,
            $currentBrand ? ['label' => $currentBrand->name, 'url' => $shopUrl([], ['marca'])] : null,
            ($priceMin || $priceMax) ? [
                'label' => __('site.shop_price').': '.($priceMin ? \App\Support\Money::format($priceMin) : '…').' – '.($priceMax ? \App\Support\Money::format($priceMax) : '…'),
                'url' => $shopUrl([], ['prezzo_min', 'prezzo_max']),
            ] : null,
            request()->boolean('disponibili') ? ['label' => __('site.shop_in_stock'), 'url' => $shopUrl([], ['disponibili'])] : null,
            request()->boolean('kmoney') ? ['label' => __('site.shop_kmoney'), 'url' => $shopUrl([], ['kmoney'])] : null,
            request()->boolean('offerta') ? ['label' => __('site.shop_on_sale'), 'url' => $shopUrl([], ['offerta'])] : null,
        ]));

        $sortOptions = [
            '' => __('site.shop_sort_newest'),
            'prezzo' => __('site.shop_sort_price_asc'),
            'prezzo_desc' => __('site.shop_sort_price_desc'),
            'nome' => __('site.shop_sort_name'),
        ];
    @endphp

    @include('pages.products.partials.storefront')

    @php
        // Il catalogo con i filtri si puo' spegnere per dominio, ma resta se si sta gia' filtrando.
        $catalog = $tenant->content()->catalog();
    @endphp

    @if ($catalog['enabled'] || request()->hasAny(['categoria', 'cerca', 'marca', 'offerta', 'page']))
    <section class="ksm-section ksm-section--tight ksm-store-catalog" id="catalogo">
        <div class="ksm-container">
            <div class="ksm-section-head ksm-shop__head">
                <div>
                    <h2>{{ $currentCategory?->name ?? $catalog['title'] ?? __('storefront.catalog') }}</h2>
                    <p>{{ __('site.shop_products_count', ['count' => $products->total()]) }}</p>
                </div>
                <div class="ksm-store-search" role="search">
                    <x-icon name="search" :size="20" />
                    <input class="ksm-input" type="search" id="cerca" name="cerca" form="ksm-shop-form" value="{{ request('cerca') }}" placeholder="{{ __('storefront.search') }}" aria-label="{{ __('site.shop_search_label') }}">
                    <button class="ksm-btn ksm-btn--primary" type="submit" form="ksm-shop-form">{{ __('storefront.search_button') }}</button>
                </div>
            </div>

            <div class="ksm-shop" data-shop>
                <aside class="ksm-shop__side" id="ksm-shop-side" aria-label="{{ __('site.shop_filters') }}">
                    <div class="ksm-shop__side-head">
                        <strong>{{ __('site.shop_filters') }}</strong>
                        <button class="ksm-shop__close" type="button" data-shop-filters-toggle aria-label="{{ __('site.shop_close') }}">
                            <x-icon name="close" />
                        </button>
                    </div>

                    <nav class="ksm-shop__block" aria-label="{{ __('site.shop_categories') }}">
                        <h2 class="ksm-shop__block-title">{{ __('site.shop_categories') }}</h2>
                        <ul class="ksm-catlist">
                            <li>
                                <a href="{{ $shopUrl([], ['categoria']) }}" @if (! $currentCategory) aria-current="page" @endif>
                                    <span>{{ __('site.shop_all_products') }}</span>
                                    <span class="ksm-catlist__count">{{ $catalogTotal }}</span>
                                </a>
                            </li>
                            @foreach ($categories as $category)
                                @continue(! $category->visible_count)
                                <li>
                                    <a href="{{ $shopUrl(['categoria' => $category->id]) }}" @if ($currentCategory?->id === $category->id) aria-current="page" @endif>
                                        <span>{{ $category->name }}</span>
                                        <span class="ksm-catlist__count">{{ $category->visible_count }}</span>
                                    </a>
                                    @if ($category->children->contains(fn ($child) => $child->visible_count > 0))
                                        <ul>
                                            @foreach ($category->children as $child)
                                                @continue(! $child->visible_count)
                                                <li>
                                                    <a href="{{ $shopUrl(['categoria' => $child->id]) }}" @if ($currentCategory?->id === $child->id) aria-current="page" @endif>
                                                        <span>{{ $child->name }}</span>
                                                        <span class="ksm-catlist__count">{{ $child->visible_count }}</span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </nav>

                    <form class="ksm-shop__form" id="ksm-shop-form" method="GET" action="{{ route('products.index') }}">
                        @if ($currentCategory)
                            <input type="hidden" name="categoria" value="{{ $currentCategory->id }}">
                        @endif

                        <fieldset class="ksm-shop__block">
                            <legend class="ksm-shop__block-title">{{ __('site.shop_price') }}</legend>
                            <div class="ksm-shop__price">
                                <input class="ksm-input" type="number" name="prezzo_min" min="0" step="1"
                                       value="{{ request('prezzo_min') }}" placeholder="€ {{ (int) floor((float) $priceRange?->min) }}"
                                       aria-label="{{ __('site.shop_price_min') }}">
                                <span aria-hidden="true">–</span>
                                <input class="ksm-input" type="number" name="prezzo_max" min="0" step="1"
                                       value="{{ request('prezzo_max') }}" placeholder="€ {{ (int) ceil((float) $priceRange?->max) }}"
                                       aria-label="{{ __('site.shop_price_max') }}">
                            </div>
                        </fieldset>

                        <fieldset class="ksm-shop__block">
                            <legend class="ksm-shop__block-title">{{ __('site.shop_options') }}</legend>
                            <label class="ksm-check">
                                <input type="checkbox" name="disponibili" value="1" @checked(request()->boolean('disponibili'))>
                                <span>{{ __('site.shop_in_stock') }}</span>
                            </label>
                            <label class="ksm-check">
                                <input type="checkbox" name="kmoney" value="1" @checked(request()->boolean('kmoney'))>
                                <span>{{ __('site.shop_kmoney') }}</span>
                            </label>
                            <label class="ksm-check">
                                <input type="checkbox" name="offerta" value="1" @checked(request()->boolean('offerta'))>
                                <span>{{ __('site.shop_on_sale') }}</span>
                            </label>
                        </fieldset>

                        @if ($brands->isNotEmpty())
                            <div class="ksm-shop__block">
                                <label class="ksm-shop__block-title" for="marca">{{ __('site.shop_brand') }}</label>
                                <select class="ksm-select" id="marca" name="marca">
                                    <option value="">{{ __('site.shop_all_brands') }}</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand->id }}" @selected($currentBrand?->id === $brand->id)>{{ $brand->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="ksm-shop__actions">
                            <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">{{ __('site.shop_apply') }}</button>
                            @if ($activeFilters)
                                <a class="ksm-shop__reset" href="{{ route('products.index') }}">{{ __('site.shop_reset') }}</a>
                            @endif
                        </div>
                    </form>
                </aside>

                <div class="ksm-shop__backdrop" data-shop-filters-toggle></div>

                <div class="ksm-shop__main">
                    <div class="ksm-shop__toolbar">
                        <button class="ksm-btn ksm-btn--ghost ksm-btn--sm ksm-shop__filters-btn" type="button"
                                data-shop-filters-toggle aria-controls="ksm-shop-side" aria-expanded="false">
                            <x-icon name="sliders" :size="17" />
                            {{ __('site.shop_filters') }}
                            @if ($activeFilters)
                                <span class="ksm-shop__pill">{{ count($activeFilters) }}</span>
                            @endif
                        </button>

                        <p class="ksm-shop__summary">
                            @if ($products->total())
                                {{ __('site.shop_results', ['from' => $products->firstItem(), 'to' => $products->lastItem(), 'total' => $products->total()]) }}
                            @endif
                        </p>

                        <div class="ksm-shop__controls">
                            <label class="ksm-shop__control">
                                <span>{{ __('site.shop_sort') }}</span>
                                <select name="ordina" form="ksm-shop-form" data-shop-autosubmit>
                                    @foreach ($sortOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(request()->string('ordina')->toString() === (string) $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="ksm-shop__control">
                                <span>{{ __('site.shop_per_page') }}</span>
                                <select name="per_pagina" form="ksm-shop-form" data-shop-autosubmit>
                                    @foreach ($perPageOptions as $option)
                                        <option value="{{ $option }}" @selected($products->perPage() === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <div class="ksm-colswitch" role="group" aria-label="{{ __('site.shop_view') }}">
                                <button type="button" data-shop-cols="1" aria-pressed="false"
                                        title="{{ __('site.shop_view_list') }}" aria-label="{{ __('site.shop_view_list') }}">
                                    <x-icon name="list" :size="18" />
                                </button>
                                @foreach ([2, 3, 4, 5, 6] as $cols)
                                    <button type="button" data-shop-cols="{{ $cols }}" aria-pressed="{{ $cols === 6 ? 'true' : 'false' }}"
                                            title="{{ __('site.shop_view_cols', ['count' => $cols]) }}"
                                            aria-label="{{ __('site.shop_view_cols', ['count' => $cols]) }}">
                                        <span class="ksm-colswitch__bars" aria-hidden="true">@for ($i = 0; $i < $cols; $i++)<i></i>@endfor</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    @if ($activeFilters)
                        <ul class="ksm-shop__chips">
                            @foreach ($activeFilters as $filter)
                                <li>
                                    <a class="ksm-chip" href="{{ $filter['url'] }}" aria-label="{{ __('site.shop_remove_filter') }}: {{ $filter['label'] }}">
                                        {{ $filter['label'] }}
                                        <x-icon name="close" :size="14" />
                                    </a>
                                </li>
                            @endforeach
                            <li><a class="ksm-shop__reset" href="{{ route('products.index') }}">{{ __('site.shop_reset') }}</a></li>
                        </ul>
                    @endif

                    <x-ad-slot placement="products_above_products" />

                    @if ($products->isEmpty())
                        <div class="ksm-card ksm-shop__empty">
                            <p>{{ __('site.no_results') }}</p>
                            @if ($activeFilters)
                                <a class="ksm-btn ksm-btn--ghost" href="{{ route('products.index') }}">{{ __('site.shop_reset') }}</a>
                            @endif
                        </div>
                    @else
                        <div class="ksm-shop__grid" data-shop-grid data-cols="6">
                            @foreach ($products as $product)
                                <x-product-card :product="$product" :storefront="true" />
                            @endforeach
                        </div>
                        <script>
                            // Colonne scelte in una visita precedente, applicate prima che la griglia si veda.
                            try {
                                const cols = localStorage.getItem('ksm.shop.cols');
                                if (['1', '2', '3', '4', '5', '6'].includes(cols)) {
                                    document.querySelector('[data-shop-grid]').dataset.cols = cols;
                                }
                            } catch (e) {}
                        </script>

                        <div class="ksm-shop__pagination">{{ $products->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </section>
    @endif
@endsection
