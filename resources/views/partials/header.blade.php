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
    // Sul sito principale sottotitolo e motto si scrivono in Amministrazione, Impostazioni.
    $mainSite = ! $tenant->isNetworkSite() && ! $tenant->isCompanySite();
    $siteTagline = $siteTagline ?? $presentation['tagline'] ?? ($mainSite ? $settings->header_tagline : null) ?? ($isShopHeader ? __('header.shop_tagline') : trim(__('site.claim_line1').' '.__('site.claim_line2')));
    $siteSubline = $siteSubline ?? $presentation['subline'] ?? ($mainSite ? $settings->header_subline : null) ?? ($isShopHeader ? __('header.shop_subline') : __('site.claim_tagline'));
    $logoUrl = $logoUrl ?? $presentation['logo'] ?? ($tenant->brandLogo() ? asset('storage/'.$tenant->brandLogo()) : null);

    // Un dominio della rete puo' scrivere il proprio menu e non mostra le pagine CMS di KSM;
    // sul sito principale i menu si scrivono in Amministrazione, Menu.
    $networkSite = $tenant->isNetworkSite();
    $menu = fn (string $location) => $networkSite ? null : \App\Support\Navigation::custom($location);

    $leftMenu = $leftMenu ?? $tenant->content()->menu('left') ?? $menu('header_left');
    $rightMenu = $rightMenu ?? $tenant->content()->menu('right') ?? $menu('header_right')
        ?? ($networkSite ? [['label' => __('site.nav_contact'), 'url' => route('contact'), 'active' => request()->routeIs('contact')]] : null);

    $leftMenu = $leftMenu ?? ($isShopHeader ? [
        ['label' => __('site.nav_home'), 'url' => route('home'), 'active' => request()->routeIs('home')],
        ['label' => __('site.nav_products'), 'url' => route('products.index'), 'active' => request()->routeIs('products.*')],
        ['label' => __('site.nav_companies'), 'url' => route('companies.index'), 'active' => request()->routeIs('companies.*')],
    ] : \App\Support\Navigation::defaults('header_left'));

    $rightMenu = $rightMenu ?? \App\Support\Navigation::defaults('header_right');

    // Barra in alto, nella fascia scura: c'e' solo se in Amministrazione ha delle voci.
    $topLeft = $menu('top_left') ?? [];
    $topRight = $menu('top_right') ?? [];
@endphp

<header class="brand-header brand-header--{{ $presentation['variant'] }}" style="{{ $presentation['style'] }}" data-site-header>
    @if ($presentation['variant'] === 'artisan')
        <div class="brand-header__utility">
            <span>{{ $siteSubline }}</span>
            <a href="{{ route('contact') }}">{{ __('header.support') }}</a>
            <a href="{{ route('cart.index') }}">{{ __('site.cart') }} ({{ $cartCount }})</a>
        </div>
    @endif
    @if ($topLeft || $topRight)
        <div class="brand-header__top">
            @foreach (['left' => $topLeft, 'right' => $topRight] as $side => $items)
                <nav class="brand-header__top-nav brand-header__top-nav--{{ $side }}" aria-label="{{ __('site.nav_top') }}">
                    @foreach ($items as $item)
                        <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                           @if ($item['new_tab']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a>
                    @endforeach
                </nav>
            @endforeach
        </div>
    @endif

    <div class="brand-header__bar">
        <nav class="brand-header__nav brand-header__nav--left" aria-label="{{ __('site.nav_primary') }}">
            @foreach ($leftMenu as $item)
                <a href="{{ $item['url'] }}" class="brand-header__link @if (! empty($item['active'])) is-active @endif"
                   @if (! empty($item['active'])) aria-current="page" @endif
                   @if (! empty($item['new_tab'])) target="_blank" rel="noopener" @endif>
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
                    <a href="{{ $item['url'] }}" class="brand-header__link @if (! empty($item['active'])) is-active @endif"
                       @if (! empty($item['new_tab'])) target="_blank" rel="noopener" @endif>
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
        @foreach (array_merge($leftMenu, $rightMenu, $topLeft, $topRight) as $item)
            <a href="{{ $item['url'] }}" @if (! empty($item['new_tab'])) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a>
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

