@extends('layouts.panel')

@section('title', 'Aggiungi nomi a dominio · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>Aggiungi nomi a dominio</h1>
        <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.domain_names.index') }}">Torna all'elenco</a>
    </div>

    <form method="POST" action="{{ route('admin.domain_names.import.store') }}" enctype="multipart/form-data">
        @csrf
        <section class="ksm-box">
            <div class="ksm-formgrid">
                <div class="ksm-field ksm-field--wide">
                    <label class="ksm-label" for="elenco">Elenco dei nomi</label>
                    <textarea class="ksm-textarea" id="elenco" name="elenco" rows="14"
                              placeholder="www.pub.it&#10;www.pizzerie.it&#10;ristorantikosher.com">{{ old('elenco') }}</textarea>
                    <p class="ksm-muted">Uno per riga (vanno bene anche virgole o spazi). "www." e maiuscole si sistemano da soli, i nomi già presenti si saltano.</p>
                    @error('elenco') <p class="ksm-error">{{ $message }}</p> @enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="file">Oppure un file .txt o .csv</label>
                    <input class="ksm-input" id="file" name="file" type="file" accept=".txt,.csv">
                    @error('file') <p class="ksm-error">{{ $message }}</p> @enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="category">Tema</label>
                    <select class="ksm-select" id="category" name="category">
                        <option value="">Scelto in automatico dal nome</option>
                        @foreach ($themes as $theme)
                            <option value="{{ $theme }}" @selected(old('category') === $theme)>{{ $theme }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <p><button class="ksm-btn ksm-btn--primary" type="submit">Aggiungi</button></p>
    </form>
@endsection
