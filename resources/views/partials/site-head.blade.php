@php
    /*
     * Icona e colori del sito corrente. Su un dominio della rete l'icona e'
     * la sua (o il logo) e i colori del dominio valgono per tutta la pagina.
     */
    $networkDomain = $tenant->domain();
    $favicon = $networkDomain
        ? ($networkDomain->favicon ?? $networkDomain->logo)
        : ($settings->favicon ?? null);
    $siteColors = $tenant->content()->cssVariables();
@endphp

@if ($favicon)
    <link rel="icon" href="{{ asset('storage/'.$favicon) }}">
@endif

@if ($siteColors)
    <style>:root{ {{ $siteColors }} }</style>
@endif
