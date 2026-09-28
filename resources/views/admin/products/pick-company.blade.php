@extends('layouts.panel')

@section('title', 'Nuovo prodotto · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <div>
            <h1>Nuovo prodotto</h1>
            <p class="ksm-panel__lead">Ogni prodotto e' di un'azienda: cerca quella a cui aggiungerlo.</p>
        </div>
        <div class="ksm-rowactions">
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.products.index') }}">Torna ai prodotti</a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.products.create') }}" class="ksm-listhead__filters" role="search" style="margin-bottom: 18px;">
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
                            <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route('admin.products.create', ['azienda' => $company->id]) }}">Aggiungi prodotto</a>
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
