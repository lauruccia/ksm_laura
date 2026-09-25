@extends('layouts.panel')

@section('title', 'Modifica '.$product->name.' · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <div>
            <h1>{{ $product->name }}</h1>
            <p class="ksm-panel__lead">
                Prodotto di
                @can(\App\Support\Permissions::COMPANIES_MANAGE)
                    <a href="{{ route('admin.companies.edit', $product->company_id) }}">{{ $product->company?->name }}</a>
                @else
                    <strong>{{ $product->company?->name }}</strong>
                @endcan
                <span class="ksm-badge @if ($product->status !== 'active') ksm-badge--muted @endif" style="margin-left: 6px;">
                    {{ $product->status === 'active' ? 'Attivo' : 'Non attivo' }}
                </span>
            </p>
        </div>
        <div class="ksm-rowactions">
            @if ($product->status === 'active' && $product->slug)
                <a class="ksm-btn ksm-btn--ghost" href="{{ route('products.show', $product->slug) }}" target="_blank" rel="noopener">Vedi sul sito</a>
            @endif
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.products.index', ['azienda' => $product->company_id]) }}">Torna ai prodotti</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        @include('products._fields', ['cancelUrl' => route('admin.products.index', ['azienda' => $product->company_id])])
    </form>
@endsection
