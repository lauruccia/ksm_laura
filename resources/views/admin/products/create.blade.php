@extends('layouts.panel')

@section('title', 'Nuovo prodotto · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <div>
            <h1>Nuovo prodotto</h1>
            <p class="ksm-panel__lead">
                Per <strong>{{ $company->name }}</strong>
                · <a href="{{ route('admin.products.create') }}">cambia azienda</a>
            </p>
        </div>
        <div class="ksm-rowactions">
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.products.index', ['azienda' => $company->id]) }}">Torna ai prodotti</a>
        </div>
    </div>

    @unless ($sells)
        <p class="ksm-alert ksm-alert--info" role="status">
            Il piano di quest'azienda non comprende la vendita: il prodotto si salva, ma sul sito non compare finche' il piano non la permette.
        </p>
    @endunless

    <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="company_id" value="{{ $company->id }}">

        @include('products._fields', ['cancelUrl' => route('admin.products.index', ['azienda' => $company->id])])
    </form>
@endsection
