@extends('layouts.panel')

@section('title', 'Nuovo ordine · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <div>
            <h1>Nuovo ordine</h1>
            <p class="ksm-panel__lead">Un ordine e' sempre per un'azienda: cerca quella che vende.</p>
        </div>
        <div class="ksm-rowactions">
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.orders.index') }}">Torna agli ordini</a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.orders.create') }}" class="ksm-listhead__filters" role="search" style="margin-bottom: 18px;">
        <span class="ksm-listhead__search">
            <x-icon name="search" :size="16" />
            <input class="ksm-input" type="search" name="cerca" value="{{ $term }}" placeholder="Nome dell'azienda" aria-label="Nome dell'azienda" autofocus>
        </span>
        <button class="ksm-btn ksm-btn--primary ksm-btn--sm" type="submit">Cerca</button>
    </form>

    @if ($term !== '')
        <div class="ksm-table-wrap">
            <table class="ksm-table">
                <thead><tr><th>Azienda</th><th>Citta'</th><th>Stato</th><th></th></tr></thead>
                <tbody>
                @forelse ($companies as $company)
                    <tr>
                        <td style="font-weight: 600;">{{ $company->name }}</td>
                        <td>{{ $company->city }}</td>
                        <td><span class="ksm-badge @unless ($company->is_active) ksm-badge--muted @endunless">{{ $company->is_active ? 'Attiva' : 'Spenta' }}</span></td>
                        <td style="text-align: right;">
                            <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route('admin.orders.create', ['azienda' => $company->id]) }}">Crea ordine</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="ksm-muted">Nessuna azienda con questo nome.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif
@endsection
