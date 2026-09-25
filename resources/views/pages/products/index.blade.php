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

        // Il catalogo con i filtri si puo' spegnere per dominio, ma resta se si sta gia' filtrando.
        $catalog = $tenant->content()->catalog();

        // Menu laterale come quello delle aziende: solo le categorie con prodotti,
        // aperta quella scelta o la madre della sottocategoria scelta.
        $categoryNav = $categories->filter(fn ($category) => $category->visible_count)->map(fn ($category) => [
            'id' => $category->id,
            'name' => $category->name,
            'url' => $shopUrl(['categoria' => $category->id]),
            'current' => $currentCategory?->id === $category->id,
            'open' => in_array($currentCategory?->id, [$category->id, ...$category->children->pluck('id')], true),
            'children' => $category->children->filter(fn ($child) => $child->visible_count)->map(fn ($child) => [
                'id' => $child->id,
                'name' => $child->name,
                'url' => $shopUrl(['categoria' => $child->id]),
                'current' => $currentCategory?->id === $child->id,
                'open' => false,
                'children' => [],
            ])->values()->all(),
        ])->values()->all();
    @endphp

    {{-- Apertura, vantaggi, riquadri e vetrina sono il sito di un dominio: li
         sceglie in Amministrazione, Domini. Sul sito principale il catalogo
         apre da solo, come la directory delle aziende. --}}
    @if ($onDomain)
        @include('pages.products.partials.storefront')
    @endif

    {{-- Stessa impostazione della directory aziende: categorie a sinistra;
         ricerca, banner e griglia a destra. --}}
    @if (! $onDomain || $catalog['enabled'] || request()->hasAny(['categoria', 'cerca', 'marca', 'offerta', 'page']))
    <section class="ksm-section ksm-store-catalog @if ($onDomain) ksm-store-catalog--after-blocks @endif" id="catalogo">
        {{-- A tutta larghezza come la directory; sui domini resta nella colonna
             dei blocchi che gli stanno sopra. --}}
        <div class="ksm-container @unless ($onDomain) ksm-directory__container @endunless">
            @if ($onDomain)
                <div class="ksm-section-head ksm-shop__head">
                    <div>
                        <h2>{{ $currentCategory?->name ?? $catalog['title'] ?? __('storefront.catalog') }}</h2>
                        <p>{{ __('site.shop_products_count', ['count' => $products->total()]) }}</p>
                    </div>
                </div>
            @endif

            <div class="ksm-shop" data-shop>
                <aside class="ksm-shop__side" id="ksm-shop-side" aria-label="{{ __('site.shop_filters') }}">
                    <div class="ksm-shop__side-head">
                        <strong>{{ __('site.shop_filters') }}</strong>
                        <button class="ksm-shop__close" type="button" data-shop-filters-toggle aria-label="{{ __('site.shop_close') }}">
                            <x-icon name="close" />
                        </button>
                    </div>

                    <nav class="ksm-shop__cats" aria-labelledby="ksm-shop-cats-title">
                        <h2 class="ksm-dirnav__title" id="ksm-shop-cats-title">{{ __('site.shop_categories') }}</h2>
                        <a class="ksm-dirnav__item ksm-dirnav__all @unless ($currentCategory) is-current @endunless"
                           href="{{ $shopUrl([], ['categoria']) }}" @unless ($currentCategory) aria-current="page" @endunless>{{ __('site.shop_all_products') }}</a>
                        @include('partials.category-nav', ['nodes' => $categoryNav])
                    </nav>

                    {{-- Il modulo raccoglie anche i campi della scheda di ricerca e della
                         barra strumenti: una sola richiesta, nessun filtro perso. --}}
                    <form class="ksm-shop__form" id="ksm-shop-form" method="GET" action="{{ route('products.index') }}">
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

                <div class="ksm-shop__main ksm-directory__main">
                    {{-- Non e' un <form>: i campi appartengono a quello dei filtri, cosi'
                         una nuova ricerca non perde prezzo, marca e opzioni. --}}
                    <div class="ksm-card ksm-directory__search" role="search">
                        <div class="ksm-field ksm-directory__query">
                            <label class="ksm-label" for="cerca">{{ __('site.search_submit') }}</label>
                            <input class="ksm-input" id="cerca" name="cerca" type="search" form="ksm-shop-form"
                                   value="{{ request('cerca') }}" placeholder="{{ __('storefront.search') }}"
                                   aria-label="{{ __('site.shop_search_label') }}">
                        </div>
                        <div class="ksm-field">
                            <label class="ksm-label" for="ordina">{{ __('site.shop_sort') }}</label>
                            <select class="ksm-select" id="ordina" name="ordina" form="ksm-shop-form" data-shop-autosubmit>
                                @foreach ($sortOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(request()->string('ordina')->toString() === (string) $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Nascosta accanto al menu, ma inviata lo stesso: una nuova ricerca resta nella categoria. --}}
                        <div class="ksm-field ksm-directory__category">
                            <label class="ksm-label" for="categoria">{{ __('site.search_category') }}</label>
                            <select class="ksm-select" id="categoria" name="categoria" form="ksm-shop-form">
                                <option value="">{{ __('site.shop_all_products') }}</option>
                                {{-- Una categoria senza prodotti non e' in elenco: se e' quella scelta
                                     ci va lo stesso, altrimenti cercare la toglierebbe dall'indirizzo. --}}
                                @if ($currentCategory && ! $categories->contains(fn ($category) => $category->visible_count && ($category->id === $currentCategory->id || $category->children->contains(fn ($child) => $child->visible_count && $child->id === $currentCategory->id))))
                                    <option value="{{ $currentCategory->id }}" selected>{{ $currentCategory->name }}</option>
                                @endif
                                @foreach ($categories as $category)
                                    @continue(! $category->visible_count)
                                    <option value="{{ $category->id }}" @selected($currentCategory?->id === $category->id)>{{ $category->name }}</option>
                                    @foreach ($category->children as $child)
                                        @continue(! $child->visible_count)
                                        <option value="{{ $child->id }}" @selected($currentCategory?->id === $child->id)>&nbsp;&nbsp;{{ $child->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <button class="ksm-btn ksm-btn--primary" type="submit" form="ksm-shop-form">
                            <x-icon name="search" :size="18" />
                            {{ __('site.search_submit') }}
                        </button>
                    </div>

                    <x-ad-slot placement="products_above_products"
                               :category="request()->integer('categoria') ?: null" />

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
                                    <button type="button" data-shop-cols="{{ $cols }}" aria-pressed="{{ $cols === 4 ? 'true' : 'false' }}"
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

                    @if ($products->isEmpty())
                        <div class="ksm-card ksm-shop__empty">
                            <p>{{ __('site.no_results') }}</p>
                            @if ($activeFilters)
                                <a class="ksm-btn ksm-btn--ghost" href="{{ route('products.index') }}">{{ __('site.shop_reset') }}</a>
                            @endif
                        </div>
                    @else
                        {{-- Quattro schede per riga come in directory; chi vuole cambia dalla barra. --}}
                        <div class="ksm-shop__grid" data-shop-grid data-cols="4">
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
