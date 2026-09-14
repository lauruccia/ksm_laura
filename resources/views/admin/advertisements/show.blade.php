@extends('layouts.panel')

@section('title', $campaign->name.' · KSM')
@section('role', 'Amministrazione')
@section('crumb', 'Campagne banner')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>Statistiche</h1>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.advertisements.index') }}">Torna alle campagne</a>
            <a class="ksm-btn ksm-btn--primary" href="{{ route('admin.advertisements.edit', $campaign) }}">Modifica</a>
        </div>
    </div>

    <p class="ksm-muted">
        Inserzionista:
        @if ($campaign->advertiser)
            <a href="{{ route('admin.advertisers.edit', $campaign->advertiser) }}">{{ $campaign->advertiser->name }}</a>
        @else
            nessuno, campagna del sito
        @endif
    </p>

    @include('partials.ads.campaign-detail')
@endsection
