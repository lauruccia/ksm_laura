<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">

    <title>@yield('title', 'Pagamento') · {{ $tenant->brandName() }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    @include('partials.site-head')
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}?v={{ filemtime(public_path('css/components.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/checkout.css') }}?v={{ filemtime(public_path('css/checkout.css')) }}">
</head>
{{-- La cassa ha un guscio suo, senza menu ne' piede: chi paga non deve
     trovare uscite. Restano solo il marchio e il ritorno al carrello. --}}
<body class="ksm-co-body">
    @php
        $presentation = app(\App\Support\HeaderPresentation::class)->resolve(request(), null);
        $brandName = $presentation['name'] ?? $tenant->brandName();
        $brandLogo = $presentation['logo'] ?? ($tenant->brandLogo() ? asset('storage/'.$tenant->brandLogo()) : null);
    @endphp

    <header class="ksm-co-header">
        {{-- Stesse colonne della cassa: il marchio sopra i passaggi, il carrello sopra il riepilogo. --}}
        <div class="ksm-co-header__side ksm-co-header__side--main">
            <a class="ksm-co-header__brand" href="{{ route('home') }}">
                @if ($brandLogo)
                    <img src="{{ $brandLogo }}" alt="{{ $brandName }}">
                @else
                    <span class="ksm-co-header__wordmark">{{ $brandName }}</span>
                @endif
            </a>
        </div>
        <div class="ksm-co-header__side ksm-co-header__side--summary">
            <a class="ksm-co-header__cart" href="{{ route('cart.index') }}" aria-label="Torna al carrello" title="Torna al carrello">
                <x-icon name="cart" :size="24" />
            </a>
        </div>
    </header>

    @yield('content')
</body>
</html>
