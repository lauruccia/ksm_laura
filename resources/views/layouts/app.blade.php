<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        // Sull'indirizzo principale di un dominio titolo e descrizione li decide il dominio.
        $seo = $tenant->content()->seo();
        $onEntry = request()->routeIs('home');
        // yieldContent restituisce testo gia' protetto: lo stesso si fa con i valori del dominio.
        $pageTitle = $onEntry && $seo['title'] ? e($seo['title']) : trim($__env->yieldContent('title', $tenant->brandName()));
        $pageDescription = $onEntry && $seo['description']
            ? e($seo['description'])
            : trim($__env->yieldContent('meta_description', $tenant->isNetworkSite() ? ($seo['description'] ?? '') : ($settings->about ?? '')));
    @endphp
    <title>{!! $pageTitle !!}</title>
    <meta name="description" content="{!! $pageDescription !!}">
    {{-- Indirizzo di riferimento: senza www (lo toglie RedirectWww), senza filtri e ordinamenti, con categoria e pagina. --}}
    <link rel="canonical" href="@yield('canonical', rtrim(url()->current().'?'.http_build_query(request()->only(['categoria', 'page'])), '?'))">

    @if ($tenant->isNetworkSite())
        <meta property="og:site_name" content="{{ $tenant->brandName() }}">
        <meta property="og:title" content="{!! $pageTitle !!}">
        <meta property="og:description" content="{!! $pageDescription !!}">
        <meta property="og:url" content="{{ url()->current() }}">
        @if ($seo['image'])
            <meta property="og:image" content="{{ $seo['image'] }}">
        @endif
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600&family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}?v={{ filemtime(public_path('css/components.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/header.css') }}?v={{ filemtime(public_path('css/header.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/sections.css') }}">
    @stack('styles')
    @include('partials.site-head')
</head>
<body>
    @include('partials.header')

    <main>
        @include('partials.flash')
        @yield('content')
    </main>

    @include('partials.footer')

    <script src="{{ asset('js/ksm.js') }}?v={{ filemtime(public_path('js/ksm.js')) }}" defer></script>
    <script src="{{ asset('js/ads.js') }}?v={{ filemtime(public_path('js/ads.js')) }}" defer></script>
    @stack('scripts')
</body>
</html>
