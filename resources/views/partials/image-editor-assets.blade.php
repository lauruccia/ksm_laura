{{-- Ritaglio e alleggerimento delle immagini: si include accanto ai campi file con data-image-editor. --}}
@once
    <link rel="stylesheet" href="{{ asset('css/image-editor.css') }}?v={{ filemtime(public_path('css/image-editor.css')) }}">
    <script src="{{ asset('js/image-editor.js') }}?v={{ filemtime(public_path('js/image-editor.js')) }}" defer></script>
@endonce
