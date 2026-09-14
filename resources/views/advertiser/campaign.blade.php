@extends('layouts.panel')

@section('title', $campaign->name.' · KSM')
@section('role', 'Area inserzionista')
@section('nav')@include('advertiser.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>Statistiche</h1>
        <a class="ksm-btn ksm-btn--ghost" href="{{ route('advertiser.dashboard') }}">Torna alle campagne</a>
    </div>

    @include('partials.ads.campaign-detail')
@endsection
