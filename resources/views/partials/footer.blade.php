{{-- I domini della rete hanno un piede tutto loro, senza dati ne' pagine di KSM. --}}
@if ($tenant->isNetworkSite())
    @include('partials.footer-site')
@else
@php
    // Dati del gestore del sito: si modificano da Amministrazione, Impostazioni.
    $siteName = $settings->website_name ?? $tenant->brandName();
    $websiteLabel = $settings->website_url ? preg_replace('#^https?://#', '', rtrim($settings->website_url, '/')) : null;
    $phoneLink = $settings->contact_number ? preg_replace('/[^\d+]/', '', $settings->contact_number) : null;
    $legal = collect([
        $settings->company_name,
        $settings->vat_number ? __('site.vat_number').' '.$settings->vat_number : null,
    ])->filter()->implode(' · ');
    $social = array_filter((array) ($settings->social_links ?? []));
    $networks = ['facebook' => 'Facebook', 'instagram' => 'Instagram'];
@endphp

<footer class="ksm-footer">
    <div class="ksm-container">
        <div class="ksm-footer__grid">
            <div>
                <a class="ksm-footer__brand" href="{{ route('home') }}">
                    @if ($settings->site_logo)
                        <img src="{{ asset('storage/'.$settings->site_logo) }}" alt="{{ $siteName }}">
                    @else
                        {{ $tenant->brandName() }}
                    @endif
                </a>

                @if ($settings->about)
                    <p>{{ $settings->about }}</p>
                @endif

                @if ($legal)
                    <p class="ksm-footer__legal">{{ $legal }}</p>
                @endif
            </div>

            <div>
                <h4>{{ __('site.contacts') }}</h4>
                <ul class="ksm-footer__contacts">
                    @if ($settings->address)
                        <li>
                            <x-icon name="pin" :size="17" />
                            <span>{{ $settings->address }}</span>
                        </li>
                    @endif
                    @if ($settings->contact_number)
                        <li>
                            <x-icon name="phone" :size="17" />
                            <a href="tel:{{ $phoneLink }}">{{ $settings->contact_number }}</a>
                        </li>
                    @endif
                    @if ($settings->website_email)
                        <li>
                            <x-icon name="mail" :size="17" />
                            <a href="mailto:{{ $settings->website_email }}">{{ $settings->website_email }}</a>
                        </li>
                    @endif
                    @if ($settings->website_url)
                        <li>
                            <x-icon name="globe" :size="17" />
                            <a href="{{ $settings->website_url }}">{{ $websiteLabel }}</a>
                        </li>
                    @endif
                </ul>
            </div>

            <div>
                <h4>{{ __('site.footer_pages') }}</h4>
                {{-- Le due colonne di link si scrivono in Amministrazione, Menu. --}}
                <ul class="ksm-footer__links">
                    @foreach (\App\Support\Navigation::items('footer_pages') as $item)
                        <li><a href="{{ $item['url'] }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif><x-icon name="chevrons" :size="15" />{{ $item['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h4>{{ __('site.footer_quick_links') }}</h4>
                <ul class="ksm-footer__links">
                    @foreach (\App\Support\Navigation::items('footer_links') as $item)
                        <li><a href="{{ $item['url'] }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif><x-icon name="chevrons" :size="15" />{{ $item['label'] }}</a></li>
                    @endforeach
                    @guest
                        <li><a href="{{ route('register') }}"><x-icon name="chevrons" :size="15" />{{ __('site.sign_up') }}</a></li>
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
            <p>&copy; {{ date('Y') }} {{ $siteName }}. {{ __('site.rights_reserved') }}</p>

            @if ($social)
                <div class="ksm-footer__social">
                    @foreach ($networks as $network => $label)
                        @isset($social[$network])
                            <a href="{{ $social[$network] }}" target="_blank" rel="noopener noreferrer"
                               aria-label="{{ __('site.follow_on', ['network' => $label]) }}">
                                <x-icon :name="$network" :size="18" />
                            </a>
                        @endisset
                    @endforeach
                </div>
            @endif

            <a class="ksm-footer__top" href="#" aria-label="{{ __('site.back_to_top') }}">
                <x-icon name="arrow-up" :size="20" />
            </a>
        </div>
    </div>
</footer>
@endif
