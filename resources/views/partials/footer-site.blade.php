@php
    /*
     * Piede di un dominio della rete. Tutto viene dal dominio: marchio,
     * descrizione, recapiti, link, social e dati legali. Nulla di KSM.
     */
    $domain = $tenant->domain();
    $footer = $tenant->content()->footer();
    $logo = $tenant->brandLogo();
    $phoneLink = $domain->phone ? preg_replace('/[^\d+]/', '', $domain->phone) : null;
    $social = array_filter((array) ($domain->social_links ?? []));
    $networks = ['facebook' => 'Facebook', 'instagram' => 'Instagram'];
    $hasContacts = $domain->address || $domain->city || $domain->phone || $domain->email;
@endphp

<footer class="ksm-footer ksm-footer--site">
    <div class="ksm-container">
        <div class="ksm-footer__grid">
            <div>
                <a class="ksm-footer__brand" href="{{ route('home') }}">
                    @if ($logo)
                        <img src="{{ asset('storage/'.$logo) }}" alt="{{ $domain->name }}" loading="lazy" decoding="async">
                    @else
                        {{ $domain->name }}
                    @endif
                </a>

                @if ($footer['about'])
                    <p>{{ $footer['about'] }}</p>
                @endif
            </div>

            @if ($hasContacts)
                <div>
                    <h4>{{ __('site.contacts') }}</h4>
                    <ul class="ksm-footer__contacts">
                        @if ($domain->address || $domain->city)
                            <li>
                                <x-icon name="pin" :size="17" />
                                <span>{{ collect([$domain->address, $domain->city])->filter()->implode(', ') }}</span>
                            </li>
                        @endif
                        @if ($domain->phone)
                            <li>
                                <x-icon name="phone" :size="17" />
                                <a href="tel:{{ $phoneLink }}">{{ $domain->phone }}</a>
                            </li>
                        @endif
                        @if ($domain->email)
                            <li>
                                <x-icon name="mail" :size="17" />
                                <a href="mailto:{{ $domain->email }}">{{ $domain->email }}</a>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif

            <div>
                <h4>{{ $footer['links_title'] }}</h4>
                <ul class="ksm-footer__links">
                    @foreach ($footer['links'] as $link)
                        <li><a href="{{ $link['url'] }}"><x-icon name="chevrons" :size="15" />{{ $link['label'] }}</a></li>
                    @endforeach
                    @guest
                        <li><a href="{{ route('login') }}"><x-icon name="chevrons" :size="15" />{{ __('site.sign_in') }}</a></li>
                    @endguest
                    @auth
                        <li><a href="{{ route('account.dashboard') }}"><x-icon name="chevrons" :size="15" />{{ __('site.my_account') }}</a></li>
                    @endauth
                </ul>
            </div>

            @if ($footer['info'])
                <div>
                    <h4>{{ $footer['info_title'] }}</h4>
                    <ul class="ksm-footer__links">
                        @foreach ($footer['info'] as $link)
                            <li><a href="{{ $link['url'] }}"><x-icon name="chevrons" :size="15" />{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <div class="ksm-footer__bottom">
        <div class="ksm-container ksm-footer__bottom-inner">
            <p>
                {{ $footer['copyright'] }}
                @if ($footer['legal'])
                    <span class="ksm-footer__legal">{{ $footer['legal'] }}</span>
                @endif
            </p>

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
