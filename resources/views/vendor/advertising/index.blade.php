@extends('layouts.panel')

@section('title', 'Pubblicità · KSM')
@section('role', 'Area azienda')
@section('nav')@include('vendor.nav')@endsection

@section('content')
    <div class="ksm-panel__head"><h1>Pubblicità</h1></div>

    @if (! $advertiser)
        <p class="ksm-muted">
            Non hai campagne banner. Per comparire nel circuito di KSM, sui domini, nelle città e nelle
            categorie che scegli, contatta l'amministrazione.
        </p>
    @else
        <p class="ksm-muted">Visualizzazioni, clic e scadenze delle tue campagne banner.</p>

        @include('partials.ads.campaigns-table', ['detailRoute' => 'vendor.advertising.show'])
    @endif
@endsection
