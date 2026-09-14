@extends('layouts.panel')

@section('title', $title.' · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>{{ $title }}</h1>
        <a class="ksm-btn ksm-btn--primary" href="{{ route($routePrefix.'.create') }}">Nuovo</a>
    </div>

    <form method="GET" style="display: flex; gap: 8px; margin-bottom: 18px; max-width: 420px;">
        <input class="ksm-input" name="cerca" value="{{ request('cerca') }}" placeholder="Cerca">
        <button class="ksm-btn ksm-btn--ghost" type="submit">Cerca</button>
    </form>

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead>
            <tr>
                @foreach ($columns as $column => $label)
                    <th>{{ $label }}</th>
                @endforeach
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($records as $record)
                <tr>
                    @foreach ($columns as $column => $label)
                        <td>
                            @if (is_bool($record->$column))
                                <span class="ksm-badge @if (! $record->$column) ksm-badge--muted @endif">
                                    {{ $record->$column ? 'Si' : 'No' }}
                                </span>
                            @else
                                {{ $record->$column }}
                            @endif
                        </td>
                    @endforeach
                    <td style="text-align: right; white-space: nowrap;">
                        @foreach ($rowActions as [$actionLabel, $actionRoute, $actionMethod])
                            <form method="POST" action="{{ route($actionRoute, $record) }}" style="display: inline;">
                                @csrf @method($actionMethod)
                                <button class="ksm-btn ksm-btn--ghost" type="submit">{{ $actionLabel }}</button>
                            </form>
                        @endforeach
                        <a class="ksm-btn ksm-btn--ghost" href="{{ route($routePrefix.'.edit', $record) }}">Modifica</a>
                        <form method="POST" action="{{ route($routePrefix.'.destroy', $record) }}"
                              style="display: inline;" onsubmit="return confirm('Eliminare questo elemento?');">
                            @csrf @method('DELETE')
                            <button class="ksm-btn ksm-btn--ghost" type="submit">Elimina</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + 1 }}" class="ksm-muted">Nessun elemento.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $records->links() }}</div>
@endsection
