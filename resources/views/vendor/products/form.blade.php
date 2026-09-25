@extends('layouts.panel')

@section('title', 'Prodotto · KSM')
@section('role', 'Area azienda')
@section('nav')@include('vendor.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <div>
            <h1>{{ $product->exists ? $product->name : 'Nuovo prodotto' }}</h1>
            <p class="ksm-panel__lead">{{ $product->exists ? 'Modifica i dati del prodotto e salva.' : 'Compila i dati e salva: potrai cambiarli quando vuoi.' }}</p>
        </div>
        <div class="ksm-rowactions">
            @if ($product->exists && $product->status === 'active' && $product->slug)
                <a class="ksm-btn ksm-btn--ghost" href="{{ route('products.show', $product->slug) }}" target="_blank" rel="noopener">Vedi sul sito</a>
            @endif
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('vendor.products.index') }}">Torna all'elenco</a>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $product->exists ? route('vendor.products.update', $product) : route('vendor.products.store') }}">
        @csrf
        @if ($product->exists)
            @method('PUT')
        @endif

        @include('products._fields', ['cancelUrl' => route('vendor.products.index')])
    </form>
@endsection
