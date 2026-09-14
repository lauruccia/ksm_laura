<?php

return [

    /* Nome del marchio mostrato quando non si e' su un dominio azienda. */
    'brand_name' => env('KSM_BRAND_NAME', 'KSM'),

    /* Host che servono la piattaforma e non una vetrina. */
    'platform_hosts' => array_filter(explode(',', (string) env('KSM_PLATFORM_HOSTS', 'ksm.it,localhost,127.0.0.1'))),

    /* Lingue disponibili nel selettore di intestazione. */
    'locales' => [
        'it' => 'Italiano',
        'en' => 'English',
    ],

    /* Valuta di default del marketplace. */
    'currency' => env('KSM_CURRENCY', 'EUR'),

    /* Regioni italiane del filtro di ricerca, nell'ordine alfabetico mostrato. */
    'regions' => [
        'Abruzzo', 'Basilicata', 'Calabria', 'Campania', 'Emilia-Romagna',
        'Friuli-Venezia Giulia', 'Lazio', 'Liguria', 'Lombardia', 'Marche',
        'Molise', 'Piemonte', 'Puglia', 'Sardegna', 'Sicilia', 'Toscana',
        'Trentino-Alto Adige', 'Umbria', "Valle d'Aosta", 'Veneto',
    ],

    /*
     * Mappe OpenStreetMap. Le tessere pubbliche di openstreetmap.org vanno
     * bene per un traffico contenuto; con molte visite si passa a un
     * fornitore (MapTiler, Stadia...) cambiando solo questi valori.
     */
    'maps' => [
        'tiles' => env('KSM_MAP_TILES', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('KSM_MAP_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'),
        'geocoder' => env('KSM_GEOCODER_URL', 'https://nominatim.openstreetmap.org/search'),
        // Nominatim chiede un contatto nelle richieste.
        'contact' => env('KSM_GEOCODER_CONTACT', 'info@ksm.it'),
    ],

    /* Il server della piattaforma: i domini dei clienti devono puntare qui. */
    'server' => [
        'ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('KSM_SERVER_IPS', ''))))),
        'cname' => env('KSM_SERVER_CNAME'),
        // Chi puo' chiedere se un dominio merita un certificato: il server web della stessa macchina.
        'tls_ask_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('KSM_TLS_ASK_IPS', '127.0.0.1,::1'))))),
    ],

    /*
     * API KMoney v1, la stessa del plugin WooCommerce 2.0, per esempio
     * https://kmoney.example/api/v1. Il token e' di ogni venditore e sta
     * nelle sue impostazioni di incasso, non qui.
     */
    'kmoney' => [
        'base_url' => env('KMONEY_API_BASE_URL'),
    ],

];
