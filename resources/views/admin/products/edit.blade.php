@extends('layouts.panel')

@section('title', 'Modifica '.$product->name.' · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>Modifica prodotto</h1>
        <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.products.index', ['azienda' => $product->company_id]) }}">Torna ai prodotti</a>
    </div>

    <p class="ksm-muted">
        Prodotto di <strong>{{ $product->company?->name }}</strong>.
        @if ($product->status === 'active' && $product->slug)
            · <a href="{{ route('products.show', $product->slug) }}" target="_blank" rel="noopener">Vedi sul sito</a>
        @endif
    </p>

    <form class="ksm-card" style="padding: 24px; max-width: 820px;" method="POST"
          action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        @include('products._fields')

        <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
    </form>
@endsection
