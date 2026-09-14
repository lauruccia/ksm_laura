@extends('layouts.panel')

@section('title', $campaign->name.' · KSM')
@section('role', 'Area azienda')
@section('nav')@include('vendor.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>Statistiche</h1>
        <a class="ksm-btn ksm-btn--ghost" href="{{ route('vendor.advertising.index') }}">Torna alla pubblicità</a>
    </div>

    @include('partials.ads.campaign-detail')
@endsection
