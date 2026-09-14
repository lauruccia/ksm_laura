@extends('layouts.panel')

@section('title', 'Campagne banner · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>Campagne banner</h1>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.advertisers.index') }}">Inserzionisti</a>
            <a class="ksm-btn ksm-btn--primary" href="{{ route('admin.advertisements.create') }}">Nuova campagna</a>
        </div>
    </div>

    <form method="GET" class="ksm-filters">
        <input class="ksm-input" name="cerca" value="{{ request('cerca') }}" placeholder="Nome della campagna">
        <select class="ksm-select" name="inserzionista" aria-label="Inserzionista">
            <option value="">Tutti gli inserzionisti</option>
            @foreach ($advertisers as $id => $name)
                <option value="{{ $id }}" @selected(request('inserzionista') == $id)>{{ $name }}</option>
            @endforeach
        </select>
        <button class="ksm-btn ksm-btn--ghost" type="submit">Filtra</button>
    </form>

    @include('partials.ads.campaigns-table', [
        'detailRoute' => 'admin.advertisements.show',
        'editRoute' => 'admin.advertisements.edit',
    ])

    <div style="margin-top: 20px;">{{ $campaigns->links() }}</div>
@endsection
