@php($selected = (array) old('permissions', $role->permissions ?? []))

@extends('layouts.panel')

@section('title', ($role->exists ? $role->name : 'Nuovo ruolo').' · KSM')
@section('role', 'Amministrazione')
@section('crumb', 'Ruoli e permessi')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>{{ $role->exists ? $role->name : 'Nuovo ruolo' }}</h1>
        <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.roles.index') }}">Torna all'elenco</a>
    </div>

    <form class="ksm-formpage" method="POST"
          action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
        @csrf
        @if ($role->exists)
            @method('PUT')
        @endif

        <section class="ksm-box ksm-formpage__wide">
        <div class="ksm-box__head"><h2>Ruolo</h2></div>
        <div class="ksm-formgrid ksm-formgrid--2">
            <div class="ksm-field">
                <label class="ksm-label" for="name">Nome del ruolo</label>
                <input class="ksm-input" id="name" name="name" value="{{ old('name', $role->name) }}" required>
                @error('name')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="description">A cosa serve</label>
                <input class="ksm-input" id="description" name="description"
                       value="{{ old('description', $role->description) }}"
                       placeholder="Per esempio: risponde ai clienti e segue gli ordini">
                @error('description')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>
        </div>

        </section>

        <section class="ksm-box ksm-formpage__wide">
        <div class="ksm-box__head"><h2>Permessi</h2></div>
        @if ($role->is_system)
            <div class="ksm-alert ksm-alert--success">
                Questo è il ruolo di sistema: concede tutto e i permessi non si possono togliere.
                Si può cambiare solo il nome.
            </div>
        @else
            <div class="ksm-panel-grid" style="gap: 0 22px;">
            @foreach ($groups as $group => $permissions)
                <fieldset class="ksm-permgroup">
                    <legend>{{ $group }}</legend>

                    @foreach ($permissions as $key => $label)
                        <label class="ksm-perm">
                            <input type="checkbox" name="permissions[]" value="{{ $key }}"
                                   @checked(in_array($key, $selected, true))>
                            <span>
                                <strong>{{ $label }}</strong>
                                <small class="ksm-muted">{{ $key }}</small>
                            </span>
                        </label>
                    @endforeach
                </fieldset>
            @endforeach
            </div>

            @error('permissions')<span class="ksm-error">{{ $message }}</span>@enderror
        @endif
        </section>

        <div class="ksm-savebar">
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.roles.index') }}">Annulla</a>
            <button class="ksm-btn ksm-btn--primary" type="submit">{{ $role->exists ? 'Salva le modifiche' : 'Crea ruolo' }}</button>
        </div>
    </form>
@endsection
