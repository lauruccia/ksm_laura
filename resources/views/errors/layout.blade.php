{{--
    Pagine di errore del sito pubblico.

    Volutamente indipendenti dal layout del sito: niente impostazioni lette
    dal database e niente testata, perche' un errore 500 puo' nascere proprio
    da li'. Usano solo i fogli di stile e il nome del marchio in configurazione.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    @php
        /*
         * Sul dominio della rete l'errore porta il suo nome e i suoi colori.
         * Il contesto c'e' gia' se l'errore nasce in una pagina; per un indirizzo
         * inesistente si ricava dall'host, ma mai per gli errori del server,
         * che possono nascere proprio dal database.
         */
        $errorBrand = config('ksm.brand_name');
        $errorColors = '';

        try {
            $errorTenant = app(\App\Support\TenantContext::class);
            $clientError = isset($exception) && method_exists($exception, 'getStatusCode') && $exception->getStatusCode() < 500;

            if ($clientError && ! $errorTenant->domain() && ! $errorTenant->company()) {
                app(\App\Http\Middleware\ResolveTenant::class)->apply(request());
            }

            $errorBrand = $errorTenant->brandName();
            $errorColors = $errorTenant->content()->cssVariables();
        } catch (\Throwable $e) {
            // Nessun contesto: resta il marchio della piattaforma.
        }
    @endphp
    <title>@yield('title') · {{ $errorBrand }}</title>

    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    @if ($errorColors)
        <style>:root{ {{ $errorColors }} }</style>
    @endif
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">

    <style>
        .ksm-error-page {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 32px 20px;
            background: var(--ksm-gradient-brand);
            color: #fff;
        }

        .ksm-error {
            width: min(560px, 100%);
            text-align: center;
        }

        .ksm-error__brand {
            display: inline-block;
            margin-bottom: 28px;
            font-family: Georgia, "Times New Roman", serif;
            font-weight: 700;
            font-size: 2rem;
            letter-spacing: -.03em;
            color: #fff;
        }

        .ksm-error__code {
            margin: 0;
            font-family: var(--ksm-font-display);
            font-size: clamp(4rem, 14vw, 7rem);
            font-weight: 800;
            line-height: 1;
            color: var(--ksm-accent-light);
        }

        .ksm-error h1 {
            margin: 10px 0 12px;
            color: #fff;
        }

        .ksm-error p {
            color: rgba(255, 255, 255, .86);
        }

        .ksm-error__actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
            margin-top: 26px;
        }
    </style>
</head>
<body class="ksm-error-page">
    <main class="ksm-error">
        <a class="ksm-error__brand" href="{{ url('/') }}">{{ $errorBrand }}</a>

        <p class="ksm-error__code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>

        <div class="ksm-error__actions">
            <a class="ksm-btn ksm-btn--primary" href="{{ url('/') }}">Torna alla home</a>
            @yield('secondary')
        </div>
    </main>
</body>
</html>
