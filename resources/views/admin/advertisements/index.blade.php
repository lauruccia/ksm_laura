@extends('layouts.panel')

@section('title', 'Campagne banner · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    {{-- Titolo, numero e filtri su una riga sola: l'elenco parte subito sotto. --}}
    <div class="ksm-listhead">
        <h1>Campagne banner <span class="ksm-listhead__count">{{ number_format($campaigns->total(), 0, ',', '.') }}</span></h1>

        <form method="GET" class="ksm-listhead__filters" role="search">
            <span class="ksm-listhead__search">
                <x-icon name="search" :size="16" />
                <input class="ksm-input" type="search" name="cerca" value="{{ request('cerca') }}" placeholder="Nome della campagna" aria-label="Nome della campagna">
            </span>
            <select class="ksm-select" name="inserzionista" aria-label="Inserzionista" onchange="this.form.submit()">
                <option value="">Tutti gli inserzionisti</option>
                @foreach ($advertisers as $id => $name)
                    <option value="{{ $id }}" @selected(request('inserzionista') == $id)>{{ $name }}</option>
                @endforeach
            </select>
            <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Cerca</button>
            @if (request()->hasAny(['cerca', 'inserzionista']))
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.advertisements.index') }}">Azzera</a>
            @endif
        </form>

        <span class="ksm-listhead__actions">
            <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.advertisers.index') }}">Inserzionisti</a>
            <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route('admin.advertisements.create') }}">Nuova campagna</a>
        </span>
    </div>

    @include('partials.ads.campaigns-table', [
        'detailRoute' => 'admin.advertisements.show',
        'editRoute' => 'admin.advertisements.edit',
    ])

    <div style="margin-top: 20px;">{{ $campaigns->links() }}</div>
@endsection
