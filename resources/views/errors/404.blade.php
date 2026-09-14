@extends('errors.layout')

@section('code', '404')
@section('title', 'Pagina non trovata')
@section('message', "L'indirizzo che hai aperto non esiste o non è più disponibile.")
@section('secondary')
    <a class="ksm-btn ksm-btn--on-dark" href="{{ url('/aziende') }}">Cerca un'azienda</a>
@endsection
