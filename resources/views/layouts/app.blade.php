<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $tenant->brandName())</title>
    <meta name="description" content="@yield('meta_description', $settings->about ?? '')">

    @if ($settings->favicon ?? null)
        <link rel="icon" href="{{ asset('storage/'.$settings->favicon) }}">
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
