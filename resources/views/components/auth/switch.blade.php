@props(['active'])

{{-- Passaggio fra le due registrazioni, senza tornare alla scelta. --}}
{{-- Le aziende si iscrivono solo sul sito principale: altrove non c'e' niente fra cui passare. --}}
@if ($tenant->isPlatformSite())
<nav class="ksm-auth__switch" aria-label="Tipo di account">
    <a href="{{ route('register.buyer') }}" @class(['is-active' => $active === 'buyer'])
       @if ($active === 'buyer') aria-current="page" @endif>
        <x-icon name="user" :size="17" /> Privato
    </a>
    <a href="{{ route('register.vendor') }}" @class(['is-active' => $active === 'vendor'])
       @if ($active === 'vendor') aria-current="page" @endif>
        <x-icon name="building" :size="17" /> Azienda
    </a>
</nav>
@endif
