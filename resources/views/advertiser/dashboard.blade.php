@extends('layouts.panel')

@section('title', 'Campagne · KSM')
@section('role', 'Area inserzionista')
@section('nav')@include('advertiser.nav')@endsection

@section('content')
    <div class="ksm-panel__head"><h1>Le tue campagne</h1></div>

    <p class="ksm-muted">
        {{ $advertiser->name }} · Le campagne le attiva l'amministrazione di KSM. Qui trovi visualizzazioni,
        clic e scadenze, aggiornati a ogni visita.
    </p>

    @include('partials.ads.campaigns-table', ['detailRoute' => 'advertiser.campaigns.show'])
@endsection
