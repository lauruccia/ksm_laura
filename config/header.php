<?php

return [
    // marketplace: blu, CTA registrazione; shop: blu, carrello; artisan: verde, carrello.
    'default' => 'marketplace',

    // Host senza protocollo o www. Ogni voce puo' definire variant, name, tagline, subline, logo.
    // 'mozzarelle.example' => ['variant' => 'shop', 'name' => 'Mozzarelle di bufala'],
    'domains' => [],

    // La prima regola corrispondente prevale sul dominio. host e' facoltativo.
    // ['path' => 'shop*', 'variant' => 'shop', 'name' => 'Mozzarelle di bufala'],
    'pages' => [],
];
