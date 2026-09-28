@php
    /*
     * Icona e colori del sito corrente. Su un dominio della rete l'icona e'
     * la sua (o il logo) e i colori del dominio valgono per tutta la pagina.
     */
    $networkDomain = $tenant->domain();
    // Sul dominio di un'azienda l'icona e' il suo logo: quella di KSM la tradirebbe.
    $favicon = match (true) {
        (bool) $networkDomain => $networkDomain->favicon ?? $networkDomain->logo,
        $tenant->isCompanySite() => $tenant->company()->logo,
        default => $settings->favicon ?? null,
    };
    $siteColors = $tenant->content()->cssVariables();
@endphp

@if ($favicon)
    <link rel="icon" href="{{ asset('storage/'.$favicon) }}">
@endif

@if ($siteColors)
    <style>:root{ {{ $siteColors }} }</style>
@endif
