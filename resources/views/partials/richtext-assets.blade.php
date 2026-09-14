{{-- L'editor delle descrizioni: si include accanto a una casella con data-richtext. --}}
@once
    <script src="{{ asset('js/richtext.js') }}?v={{ filemtime(public_path('js/richtext.js')) }}" defer></script>
@endonce
