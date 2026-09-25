@extends('layouts.panel')

@section('title', 'Prodotto · KSM')
@section('role', 'Area azienda')
@section('nav')@include('vendor.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>{{ $product->exists ? 'Modifica prodotto' : 'Nuovo prodotto' }}</h1>
        <a class="ksm-btn ksm-btn--ghost" href="{{ route('vendor.products.index') }}">Torna all'elenco</a>
    </div>

    <form class="ksm-card" style="padding: 24px; max-width: 820px;" method="POST"
          action="{{ $product->exists ? route('vendor.products.update', $product) : route('vendor.products.store') }}"
          enctype="multipart/form-data">
        @csrf
        @if ($product->exists)
            @method('PUT')
        @endif

        @include('products._fields')

        <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
    </form>
@endsection
