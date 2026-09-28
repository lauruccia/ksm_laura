@php
    /*
     * Piede del dominio proprio di un'azienda. Tutto viene dall'azienda:
     * nome, logo, recapiti e sito. Nulla di KSM.
     */
    $company = $tenant->company();
    $logo = $tenant->brandLogo();
    $phoneLink = $company->phone ? preg_replace('/[^\d+]/', '', $company->phone) : null;
    $place = collect([$company->address, $company->city, $company->region])->filter()->unique()->implode(', ');
    $sells = $company->allows(\App\Support\PlanCapabilities::SHOP);
@endphp

<footer class="ksm-footer ksm-footer--site">
    <div class="ksm-container">
        <div class="ksm-footer__grid">
            <div>
                <a class="ksm-footer__brand" href="{{ route('home') }}">
                    @if ($logo)
                        <img src="{{ asset('storage/'.$logo) }}" alt="{{ $company->name }}" loading="lazy" decoding="async">
                    @else
                        {{ $company->name }}
                    @endif
                </a>
                @if ($company->category)
                    <p>{{ $company->category->name }}</p>
                @endif
            </div>

            @if ($place || $company->phone || $company->email || $company->website)
                <div>
                    <h4>{{ __('site.contacts') }}</h4>
                    <ul class="ksm-footer__contacts">
                        @if ($place)
                            <li><x-icon name="pin" :size="17" /><span>{{ $place }}</span></li>
                        @endif
                        @if ($company->phone)
                            <li><x-icon name="phone" :size="17" /><a href="tel:{{ $phoneLink }}">{{ $company->phone }}</a></li>
                        @endif
                        @if ($company->email)
                            <li><x-icon name="mail" :size="17" /><a href="mailto:{{ $company->email }}">{{ $company->email }}</a></li>
                        @endif
                        @if ($company->website)
                            <li><x-icon name="globe" :size="17" /><a href="{{ $company->website }}" rel="noopener">{{ preg_replace('#^https?://#', '', rtrim($company->website, '/')) }}</a></li>
                        @endif
                    </ul>
                </div>
            @endif

            <div>
                <h4>{{ __('site.footer_quick_links') }}</h4>
                <ul class="ksm-footer__links">
                    <li><a href="{{ route('home') }}"><x-icon name="chevrons" :size="15" />{{ __('site.nav_home') }}</a></li>
                    @if ($sells)
                        <li><a href="{{ route('products.index') }}"><x-icon name="chevrons" :size="15" />{{ __('site.nav_products') }}</a></li>
                        <li><a href="{{ route('orders.track') }}"><x-icon name="chevrons" :size="15" />{{ __('site.track_order') }}</a></li>
                    @endif
                    <li><a href="{{ route('contact') }}"><x-icon name="chevrons" :size="15" />{{ __('site.nav_contact') }}</a></li>
                    @guest
                        <li><a href="{{ route('login') }}"><x-icon name="chevrons" :size="15" />{{ __('site.sign_in') }}</a></li>
                    @endguest
                    @auth
                        <li><a href="{{ route('account.dashboard') }}"><x-icon name="chevrons" :size="15" />{{ __('site.my_account') }}</a></li>
                    @endauth
                </ul>
            </div>
        </div>
    </div>

    <div class="ksm-footer__bottom">
        <div class="ksm-container ksm-footer__bottom-inner">
            <p>&copy; {{ date('Y') }} {{ $company->name }}. {{ __('site.rights_reserved') }}</p>

            <a class="ksm-footer__top" href="#" aria-label="{{ __('site.back_to_top') }}">
                <x-icon name="arrow-up" :size="20" />
            </a>
        </div>
    </div>
</footer>
