@php
    /*
     * Testata del sito pubblico.
     *
     * Nome, sottotitolo e logo si possono cambiare pagina per pagina:
     * basta che la vista riceva $siteName, $siteTagline, $siteSubline
     * o $logoUrl. Senza, valgono quelli del dominio corrente.
     */
    $presentation = app(\App\Support\HeaderPresentation::class)->resolve(request(), $headerVariant ?? null);
    $isShopHeader = $presentation['shop'];
    $siteName = $siteName ?? $presentation['name'] ?? $tenant->brandName();
    $siteTagline = $siteTagline ?? $presentation['tagline'] ?? ($isShopHeader ? __('header.shop_tagline') : trim(__('site.claim_line1').' '.__('site.claim_line2')));
    $siteSubline = $siteSubline ?? $presentation['subline'] ?? ($isShopHeader ? __('header.shop_subline') : __('site.claim_tagline'));
    $logoUrl = $logoUrl ?? $presentation['logo'] ?? ($tenant->brandLogo() ? asset('storage/'.$tenant->brandLogo()) : null);

    // Un dominio della rete puo' scrivere il proprio menu e non mostra le pagine CMS di KSM.
    $networkSite = $tenant->isNetworkSite();
    $leftMenu = $leftMenu ?? $tenant->content()->menu('left');
    $rightMenu = $rightMenu ?? $tenant->content()->menu('right')
        ?? ($networkSite ? [['label' => __('site.nav_contact'), 'url' => route('contact'), 'active' => request()->routeIs('contact')]] : null);

    $leftMenu = $leftMenu ?? ($isShopHeader ? [
        ['label' => __('site.nav_home'), 'url' => route('home'), 'active' => request()->routeIs('home')],
        ['label' => __('site.nav_products'), 'url' => route('products.index'), 'active' => request()->routeIs('products.*')],
        ['label' => __('site.nav_companies'), 'url' => route('companies.index'), 'active' => request()->routeIs('companies.*')],
    ] : [
        ['label' => __('site.nav_home'), 'url' => route('home'), 'active' => request()->routeIs('home')],
        ['label' => __('site.nav_companies'), 'url' => route('companies.index'), 'active' => request()->routeIs('companies.*')],
        ['label' => __('site.nav_products'), 'url' => route('products.index'), 'active' => request()->routeIs('products.*')],
        ['label' => __('site.nav_plans'), 'url' => route('plans.index'), 'active' => request()->routeIs('plans.*')],
    ]);

    $rightMenu = $rightMenu ?? array_merge(
        [['label' => __('site.nav_contact'), 'url' => route('contact'), 'active' => request()->routeIs('contact')]],
        $headerPages->map(fn ($page) => [
            'label' => $page->title,
            'url' => route('pages.show', $page->slug),
            'active' => false,
        ])->all()
    );
@endphp

<header class="brand-header brand-header--{{ $presentation['variant'] }}" style="{{ $presentation['style'] }}" data-site-header>
    @if ($presentation['variant'] === 'artisan')
        <div class="brand-header__utility">
            <span>{{ $siteSubline }}</span>
            <a href="{{ route('contact') }}">{{ __('header.support') }}</a>
            <a href="{{ route('cart.index') }}">{{ __('site.cart') }} ({{ $cartCount }})</a>
        </div>
    @endif
    <div class="brand-header__accent brand-header__accent--left" aria-hidden="true"></div>
    <div class="brand-header__accent brand-header__accent--right" aria-hidden="true"></div>

    <div class="brand-header__bar">
        <nav class="brand-header__nav brand-header__nav--left" aria-label="{{ __('site.nav_primary') }}">
            @foreach ($leftMenu as $item)
                <a href="{{ $item['url'] }}" class="brand-header__link @if (! empty($item['active'])) is-active @endif"
                   @if (! empty($item['active'])) aria-current="page" @endif>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <a href="{{ route('home') }}" class="brand-header__brand" aria-label="{{ $siteName }}">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $siteName }}" class="brand-header__logo">
            @else
                <span class="brand-header__fallback @if (mb_strlen($siteName) > 12) brand-header__fallback--long @endif">{{ $siteName }}</span>
            @endif

            <span class="brand-header__tagline">{{ $siteTagline }}</span>
            {{-- I separatori della sigla sono resi a parte per colorarli di verde. --}}
            {{-- Su una riga sola: gli a capo aggiungerebbero spazio prima dei punti. --}}
            <span class="brand-header__subline">@foreach (preg_split('/\s*[·•]\s*/u', $siteSubline) as $i => $part)@if ($i > 0)<span class="brand-header__dot" aria-hidden="true">•</span><span class="brand-header__gap"> </span>@endif{{ $part }}@endforeach</span>
        </a>

        <div class="brand-header__right">
            <nav class="brand-header__nav brand-header__nav--right" aria-label="{{ __('site.nav_secondary') }}">
                @foreach ($rightMenu as $item)
                    <a href="{{ $item['url'] }}" class="brand-header__link @if (! empty($item['active'])) is-active @endif">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <button class="brand-header__icon-btn" type="button" data-search-toggle aria-expanded="false"
                    aria-controls="ksm-navsearch" aria-label="{{ __('site.search_submit') }}">
                <x-icon name="search" :size="20" />
            </button>

            {{-- Il numero conta i pezzi di tutti i carrelli: i venditori si scelgono nel carrello. --}}
            <a href="{{ route('cart.index') }}" class="brand-header__icon-btn brand-header__cart"
               aria-label="{{ __('site.cart') }} ({{ $cartCount }}){{ $cartVendors > 1 ? ' · '.$cartVendors.' venditori' : '' }}"
               title="{{ __('site.cart') }}{{ $cartVendors > 1 ? ' · '.$cartVendors.' venditori' : '' }}">
                <x-icon name="cart" :size="22" />
                <span class="brand-header__cart-count" aria-hidden="true">{{ $cartCount }}</span>
            </a>

            @guest
                <a href="{{ route('login') }}" class="brand-header__soft-btn">{{ __('site.sign_in') }}</a>
            @endguest

            @auth
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="brand-header__soft-btn brand-header__soft-btn--account"><x-icon name="user" :size="20" /><span>{{ __('site.admin') }}</span></a>
                @elseif (auth()->user()->hasActiveCompany())
                    <a href="{{ route('vendor.dashboard') }}" class="brand-header__soft-btn brand-header__soft-btn--account"><x-icon name="user" :size="20" /><span>{{ __('site.my_area') }}</span></a>
                @else
                    <a href="{{ route('account.dashboard') }}" class="brand-header__soft-btn brand-header__soft-btn--account"><x-icon name="user" :size="20" /><span>{{ __('site.my_account') }}</span></a>
                @endif
            @endauth

            @unless ($isShopHeader || $networkSite)
                <a href="{{ route('register.vendor') }}" class="brand-header__register-btn" aria-label="{{ __('site.register_company') }}">
                    <x-icon name="building" :size="20" />
                    <span>{{ __('site.register_company') }}</span>
                </a>
            @endunless

            <div class="brand-header__lang">
                <select onchange="location.href = this.value;" aria-label="{{ __('site.language') }}">
                    @foreach (config('ksm.locales') as $code => $label)
                        <option value="{{ route('locale.switch', $code) }}" @selected(app()->getLocale() === $code)>
                            {{ strtoupper($code) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button class="brand-header__mobile-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="ksm-mobile-menu"
                    aria-label="{{ __('site.menu') }}">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>

    <div class="brand-header__mobile-menu" id="ksm-mobile-menu">
        @foreach (array_merge($leftMenu, $rightMenu) as $item)
            <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
        @endforeach

        @guest
            <a href="{{ route('login') }}">{{ __('site.sign_in') }}</a>
            @unless ($networkSite)
                <a href="{{ route('register.vendor') }}">{{ __('site.register_company') }}</a>
            @endunless
        @endguest

        @auth
            <a href="{{ route('account.dashboard') }}">{{ __('site.my_account') }}</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">{{ __('site.sign_out') }}</button>
            </form>
        @endauth

        <a href="{{ route('cart.index') }}">{{ __('site.cart') }} ({{ $cartCount }})</a>
    </div>

    <div class="brand-header__search" id="ksm-navsearch">
        <form method="GET" action="{{ $isShopHeader ? route('products.index') : route('companies.index') }}">
            <x-icon name="search" />
            <input type="text" name="cerca" value="{{ request('cerca') }}"
                   placeholder="{{ __('site.search_placeholder') }}" aria-label="{{ __('site.search_placeholder') }}">
            <button type="submit">{{ __('site.search_submit') }}</button>
        </form>
    </div>
</header>

