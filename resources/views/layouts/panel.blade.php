<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $tenant->brandName())</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}?v={{ filemtime(public_path('css/tokens.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/base.css') }}?v={{ filemtime(public_path('css/base.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}?v={{ filemtime(public_path('css/components.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/panel.css') }}?v={{ filemtime(public_path('css/panel.css')) }}">
    <script src="{{ asset('js/tables.js') }}?v={{ filemtime(public_path('js/tables.js')) }}" defer></script>
</head>
<body>
{{-- La spunta nascosta apre e chiude la colonna sui telefoni: niente script. --}}
<input class="ksm-panel__switch" id="ksm-panel-switch" type="checkbox" hidden>

<div class="ksm-panel">
    <aside class="ksm-panel__side">
        <div class="ksm-panel__brandrow">
            <a class="ksm-panel__brand" href="{{ route('home') }}">
                KSM
                <span class="ksm-panel__role">@yield('role')</span>
            </a>
        </div>

        <nav class="ksm-panel__navwrap">
            <ul class="ksm-panel__nav">
                @yield('nav')
            </ul>
        </nav>
    </aside>

    <label class="ksm-panel__shade" for="ksm-panel-switch" aria-hidden="true"></label>

    <div class="ksm-panel__body">
        <header class="ksm-panel__top">
            <label class="ksm-panel__burger" for="ksm-panel-switch" aria-label="Menu">
                <span></span>
            </label>

            <span class="ksm-panel__crumb">
                @hasSection('crumb')
                    @yield('crumb')
                @else
                    @yield('role')
                @endif
            </span>

            <div class="ksm-panel__topactions">
                @yield('topactions')
                <a class="ksm-panel__toplink" href="{{ route('home') }}" title="Vai al sito">
                    <x-icon name="external" :size="17" /><span>Vai al sito</span>
                </a>

                @auth
                    {{-- Chi ha fatto accesso e l'uscita, in alto a destra come in ogni gestionale. --}}
                    @php
                        $profileUrl = match (true) {
                            request()->routeIs('admin.*') => route('admin.profile.edit'),
                            request()->routeIs('account.*') => route('account.profile.edit'),
                            default => null,
                        };
                    @endphp
                    <span class="ksm-panel__divider" aria-hidden="true"></span>
                    @if ($profileUrl)
                        <a class="ksm-panel__me" href="{{ $profileUrl }}" title="Il mio profilo">
                    @else
                        <span class="ksm-panel__me">
                    @endif
                        <span class="ksm-panel__avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="ksm-panel__meinfo">
                            <strong>{{ auth()->user()->name }}</strong>
                            <small>@yield('me', auth()->user()->role?->name ?? auth()->user()->typeLabel())</small>
                        </span>
                    @if ($profileUrl)
                        </a>
                    @else
                        </span>
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="ksm-panel__logout" type="submit" title="Esci">
                            <x-icon name="logout" :size="17" /><span>Esci</span>
                        </button>
                    </form>
                @endauth
            </div>
        </header>

        <main class="ksm-panel__main">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
