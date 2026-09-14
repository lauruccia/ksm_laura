@extends('layouts.panel')

@section('title', $title.' · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>{{ $record->exists ? 'Modifica' : 'Nuovo' }} · {{ $title }}</h1>
        <a class="ksm-btn ksm-btn--ghost" href="{{ route($routePrefix.'.index') }}">Torna all'elenco</a>
    </div>

    <form class="ksm-card" style="padding: 24px; max-width: 780px;" method="POST"
          action="{{ $record->exists ? route($routePrefix.'.update', $record) : route($routePrefix.'.store') }}"
          enctype="multipart/form-data">
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        @foreach ($fields as $name => $field)
            @php($value = old($name, $record->$name))
            @php($type = $field['type'])

            <div class="ksm-field">
                @if ($type !== 'checkbox')
                    <label class="ksm-label" for="{{ $name }}">{{ $field['label'] }}</label>
                @endif

                @switch($type)
                    @case('textarea')
                        <textarea class="ksm-textarea" id="{{ $name }}" name="{{ $name }}"
                                  rows="{{ $field['rows'] ?? 4 }}">{{ $value }}</textarea>
                        @break

                    @case('select')
                        <select class="ksm-select" id="{{ $name }}" name="{{ $name }}">
                            @isset($field['empty'])
                                <option value="">{{ $field['empty'] }}</option>
                            @endisset
                            @foreach ($options[$name] ?? [] as $key => $label)
                                <option value="{{ $key }}" @selected((string) $value === (string) $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @break

                    @case('checkbox')
                        <label class="ksm-label" for="{{ $name }}" style="display: flex; gap: 8px; align-items: center;">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input id="{{ $name }}" name="{{ $name }}" type="checkbox" value="1" @checked($value)>
                            {{ $field['label'] }}
                        </label>
                        @break

                    @case('checkboxes')
                        @php($selected = (array) old($name, $record->$name ?? []))
                        <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                            @foreach ($options[$name] ?? [] as $key => $label)
                                <label style="display: flex; gap: 6px; align-items: center;">
                                    <input type="checkbox" name="{{ $name }}[]" value="{{ $key }}"
                                           @checked(in_array($key, $selected))>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @break

                    @case('list')
                        @php($values = (array) old($name, $record->$name ?? []))
                        @for ($i = 0; $i < 6; $i++)
                            <input class="ksm-input" style="margin-bottom: 6px;" name="{{ $name }}[]"
                                   value="{{ $values[$i] ?? '' }}">
                        @endfor
                        @break

                    @case('file')
                        <input class="ksm-input" id="{{ $name }}" name="{{ $name }}" type="file">
                        @break

                    @default
                        <input class="ksm-input" id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
                               value="{{ $type === 'password' ? '' : $value }}"
                               @isset($field['step']) step="{{ $field['step'] }}" @endisset>
                @endswitch

                @isset($field['hint'])
                    <small class="ksm-muted">{{ $field['hint'] }}</small>
                @endisset

                @error($name)
                    <span class="ksm-error">{{ $message }}</span>
                @enderror
            </div>
        @endforeach

        <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
    </form>
@endsection
