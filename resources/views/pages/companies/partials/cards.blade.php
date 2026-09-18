{{--
    Le schede di una pagina della directory. La pagina intera le mette nella
    griglia; il caricamento continuo riceve solo questo pezzo. Il link
    nascosto dice allo script da dove prendere la pagina successiva.
--}}
@foreach ($companies as $company)
    <x-company-card :company="$company" />
@endforeach

@if ($companies->hasMorePages())
    <a href="{{ $companies->nextPageUrl() }}" data-directory-next hidden></a>
@endif
