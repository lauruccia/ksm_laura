@extends('errors.layout')

@section('code', '403')
@section('title', 'Accesso non consentito')
@section('message', "Non hai i permessi per aprire questa pagina. Se pensi sia un errore, accedi con l'account giusto.")
@section('secondary')
    <a class="ksm-btn ksm-btn--on-dark" href="{{ url('/accedi') }}">Accedi</a>
@endsection
