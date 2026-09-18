@props(['title', 'lead' => null])

{{-- Guscio comune di accesso e registrazioni: a sinistra perche' conviene,
     a destra il modulo. Su telefono il modulo viene prima. --}}
@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
    @endpush
@endonce

<section class="ksm-section ksm-auth">
    <div class="ksm-container">
        <div class="ksm-auth__card">
            <aside class="ksm-auth__aside">
                {{ $aside }}
            </aside>

            <div class="ksm-auth__main">
                <h1 class="ksm-auth__title">{{ $title }}</h1>
                @if ($lead)
                    <p class="ksm-auth__lead">{{ $lead }}</p>
                @endif

                {{ $slot }}
            </div>
        </div>
    </div>
</section>
