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

    <form class="ksm-box" style="max-width: 820px;" method="POST"
          action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
        @csrf
        @if ($role->exists)
            @method('PUT')
        @endif

        <div class="ksm-formgrid">
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

        @if ($role->is_system)
            <div class="ksm-alert ksm-alert--success">
                Questo e il ruolo di sistema: concede tutto e i permessi non si possono togliere.
                Si può cambiare solo il nome.
            </div>
        @else
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

            @error('permissions')<span class="ksm-error">{{ $message }}</span>@enderror
        @endif

        <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
    </form>
@endsection
