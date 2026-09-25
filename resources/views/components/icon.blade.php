@props(['name', 'size' => 20])

@php
    // Set di icone lineari usato in tutto il sito: nessuna dipendenza esterna.
    $paths = [
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/>',
        'box'      => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4 7.5 8 4.5 8-4.5"/><path d="M12 12v9"/>',
        'building' => '<path d="M4 21V6a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v15"/><path d="M14 10h4a2 2 0 0 1 2 2v9"/><path d="M8 8h2"/><path d="M8 12h2"/><path d="M8 16h2"/><path d="M2 21h20"/>',
        'chart'    => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
        'users'    => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16.5 5.6a3.2 3.2 0 0 1 0 5.8"/><path d="M18 14.6A6 6 0 0 1 21.5 20"/>',
        'arrow'    => '<path d="M5 12h13"/><path d="m12.5 5.5 6 6.5-6 6.5"/>',
        'pin'      => '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>',
        'tag'      => '<path d="M3.5 11.6V4.5a1 1 0 0 1 1-1h7.1a1 1 0 0 1 .7.3l8 8a1 1 0 0 1 0 1.4l-7.1 7.1a1 1 0 0 1-1.4 0l-8-8a1 1 0 0 1-.3-.7Z"/><circle cx="7.8" cy="7.8" r="1.4"/>',
        'mail'     => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'phone'    => '<path d="M6.5 3.5h3l1.5 4-2 1.5a12 12 0 0 0 6 6l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4.5 5.7a2 2 0 0 1 2-2.2Z"/>',
        'link'     => '<path d="M10.5 13.5a4 4 0 0 0 5.7 0l2.8-2.8a4 4 0 1 0-5.7-5.7l-1.4 1.4"/><path d="M13.5 10.5a4 4 0 0 0-5.7 0l-2.8 2.8a4 4 0 1 0 5.7 5.7l1.4-1.4"/>',
        'cart'     => '<circle cx="9.5" cy="19.5" r="1.5"/><circle cx="17.5" cy="19.5" r="1.5"/><path d="M2.5 3.5h2.2l2.4 11.2a1.6 1.6 0 0 0 1.6 1.3h8.6a1.6 1.6 0 0 0 1.6-1.2l1.6-6.3H6"/>',
        'user'     => '<circle cx="12" cy="8" r="3.4"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3.2 9h17.6M3.2 15h17.6"/><path d="M12 3c2.3 2.4 3.4 5.4 3.4 9s-1.1 6.6-3.4 9c-2.3-2.4-3.4-5.4-3.4-9S9.7 5.4 12 3Z"/>',
        'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'shield'   => '<path d="M12 3 5 6v5.5c0 4.3 2.9 7.9 7 9.5 4.1-1.6 7-5.2 7-9.5V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>',
        'sparkle'  => '<path d="M12 3.5 13.7 9l5.5 1.7-5.5 1.7L12 18l-1.7-5.6L4.8 10.7 10.3 9 12 3.5Z"/>',
        'chevrons' => '<path d="m6 7 5 5-5 5"/><path d="m12 7 5 5-5 5"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'sliders'  => '<path d="M4 6h9M17 6h3M4 12h3M11 12h9M4 18h11M19 18h1"/><circle cx="15" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
        'list'     => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>',
        'close'    => '<path d="m6 6 12 12M18 6 6 18"/>',
        'arrow-up' =>'<path d="M12 19V6"/><path d="m5.5 11.5 6.5-6.5 6.5 6.5"/>',
        'facebook' => '<path d="M14 8.5h2.5V5H14a4 4 0 0 0-4 4v2H8v3.5h2V21h3.5v-6.5H16l.5-3.5h-3V9a.5.5 0 0 1 .5-.5Z"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r=".7" fill="currentColor"/>',

        // Settori delle aziende, vedi App\Support\CategoryIcon.
        'palette'   => '<path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.8-.8 1.8-1.8 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.2 0-1 .8-1.8 1.8-1.8H17a4 4 0 0 0 4-4C21 6.6 17 3 12 3Z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="15" cy="7.5" r="1"/>',
        'hammer'    => '<path d="m15 12-8.4 8.4a2.1 2.1 0 0 1-3-3L12 9"/><path d="M17.6 15 22 10.6"/><path d="m20.9 11.7-1.3-1.3a2 2 0 0 1-.6-1.4v-.9l-2.3-2.3a5.5 5.5 0 0 0-4-1.6H9l.9.8a6.2 6.2 0 0 1 2 4.5V11l2 2h.9a2 2 0 0 1 1.4.6l1.3 1.3"/>',
        'car'       => '<path d="M5 17H3.5a1 1 0 0 1-1-1v-3.2a2 2 0 0 1 .6-1.4L5 9.5l1.7-3.4A2 2 0 0 1 8.5 5h7a2 2 0 0 1 1.8 1.1L19 9.5l1.9 1.9a2 2 0 0 1 .6 1.4V16a1 1 0 0 1-1 1H19"/><path d="M9 17h6"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M5 9.5h14"/>',
        'home'      => '<path d="M3.5 10.5 12 3.5l8.5 7"/><path d="M5.5 9v11.5h13V9"/><path d="M10 20.5v-6h4v6"/>',
        'bed'       => '<path d="M3 19V5"/><path d="M3 15h18v4"/><path d="M21 15v-3a3 3 0 0 0-3-3h-7v6"/><circle cx="7" cy="11" r="2"/>',
        'monitor'   => '<rect x="2.5" y="4" width="19" height="12.5" rx="2"/><path d="M8 20.5h8M12 16.5v4"/>',
        'utensils'  => '<path d="M5 3v7a2.5 2.5 0 0 0 5 0V3"/><path d="M7.5 3v18"/><path d="M19 15V3a4.5 4.5 0 0 0-4.5 4.5V13a2 2 0 0 0 2 2H19Zm0 0v6"/>',
        'paw'       => '<circle cx="6" cy="10" r="1.8"/><circle cx="9.5" cy="5.5" r="1.8"/><circle cx="14.5" cy="5.5" r="1.8"/><circle cx="18" cy="10" r="1.8"/><path d="M12 11c-2.5 0-5.5 4-5.5 6.5 0 1.7 1.3 2.5 2.7 2.5 1.2 0 1.8-.6 2.8-.6s1.6.6 2.8.6c1.4 0 2.7-.8 2.7-2.5C17.5 15 14.5 11 12 11Z"/>',
        'briefcase' => '<rect x="2.5" y="7" width="19" height="13" rx="2"/><path d="M8.5 7V5a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2"/><path d="M2.5 12.5h19"/>',
        'gift'      => '<rect x="3" y="8" width="18" height="4.5" rx="1"/><path d="M5 12.5V21h14v-8.5"/><path d="M12 8v13"/><path d="M12 8H8a2.5 2.5 0 1 1 0-5c2.5 0 4 5 4 5Zm0 0h4a2.5 2.5 0 1 0 0-5c-2.5 0-4 5-4 5Z"/>',
        'heart'     => '<path d="M12 20s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7a4.3 4.3 0 0 1 7.5 2.8C19.5 15.4 12 20 12 20Z"/>',
        'wrench'    => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9l-3.8 3.8Z"/>',
        'shirt'     => '<path d="M8.5 3.5 3 6.5l2 4.5 2.5-1v10.5h9V10l2.5 1 2-4.5-5.5-3a3.5 3.5 0 0 1-7 0Z"/>',
        'ball'      => '<circle cx="12" cy="12" r="9"/><path d="M5.6 5.6c3.5 3.5 3.5 9.3 0 12.8M18.4 5.6c-3.5 3.5-3.5 9.3 0 12.8"/>',
        'grid'      => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'truck'     => '<path d="M2.5 6.5h11v10h-11z"/><path d="M13.5 10h4l3 3.5v3h-7"/><circle cx="6.5" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
        'award'     => '<circle cx="12" cy="9" r="5.5"/><circle cx="12" cy="9" r="2.5"/><path d="m8.5 13.3-1.8 7.2 5.3-2.6 5.3 2.6-1.8-7.2"/>',
        'leaf'      => '<path d="M20 4c-9 0-15 4.5-15 11a5 5 0 0 0 5 5c6.5 0 10-6 10-16Z"/><path d="M4 21c3-5 7-8.5 11.5-11"/>',
        'star'      => '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.9L12 3.5Z"/>',

        // Voci dei pannelli.
        'dashboard' => '<rect x="3.5" y="3.5" width="7" height="9" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="5" rx="1.5"/><rect x="13.5" y="11.5" width="7" height="9" rx="1.5"/><rect x="3.5" y="15.5" width="7" height="5" rx="1.5"/>',
        'card'      => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 9.5h19"/><path d="M6.5 15h4"/>',
        'refresh'   => '<path d="M20 11a8 8 0 0 0-14.3-4.9L4 8"/><path d="M4 3.5V8h4.5"/><path d="M4 13a8 8 0 0 0 14.3 4.9L20 16"/><path d="M20 20.5V16h-4.5"/>',
        'file'      => '<path d="M14 3.5H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.5l-5-5Z"/><path d="M14 3.5v5h5"/><path d="M9 13h6M9 16.5h6"/>',
        'menu'      => '<path d="M4 6.5h16M4 12h16M4 17.5h10"/>',
        'megaphone' => '<path d="M3.5 10v4a1 1 0 0 0 1 1H7l7 4.5v-15L7 9H4.5a1 1 0 0 0-1 1Z"/><path d="M17.5 9a4 4 0 0 1 0 6"/><path d="M8 15.5 9.5 20h2.5l-1.3-4.3"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
        'logout'    => '<path d="M9.5 20.5H5.5a2 2 0 0 1-2-2v-13a2 2 0 0 1 2-2h4"/><path d="m15.5 16.5 4.5-4.5-4.5-4.5"/><path d="M20 12H9.5"/>',
        'external'  => '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18.5 14v4.5a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2v-11a2 2 0 0 1 2-2H10"/>',
    ];
@endphp

<svg {{ $attributes->merge(['class' => 'ksm-icon', 'aria-hidden' => 'true', 'focusable' => 'false']) }}
     width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
    {!! $paths[$name] ?? '' !!}
</svg>
