@extends('layouts.panel')

@section('title', $title.' · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    {{-- La selezione in blocco c'e' solo per le anagrafiche che ne registrano la rotta. --}}
    @php($bulk = \Illuminate\Support\Facades\Route::has($routePrefix.'.bulk'))

    {{-- Titolo, numero e ricerca su una riga sola: l'elenco parte subito sotto. --}}
    <div class="ksm-listhead">
        <h1>{{ $title }} <span class="ksm-listhead__count">{{ number_format($records->total(), 0, ',', '.') }}</span></h1>

        <form method="GET" class="ksm-listhead__filters" role="search">
            <span class="ksm-listhead__search">
                <x-icon name="search" :size="16" />
                <input class="ksm-input" type="search" name="cerca" value="{{ request('cerca') }}" placeholder="Cerca" aria-label="Cerca">
            </span>
            <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Cerca</button>
            @if (request()->filled('cerca'))
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route($routePrefix.'.index') }}">Azzera</a>
            @endif
        </form>

        <span class="ksm-listhead__actions">
            <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route($routePrefix.'.create') }}">Nuovo</a>
        </span>
    </div>

    @if ($bulk && $records->isNotEmpty())
        @include('partials.bulk-bar', [
            'action' => route($routePrefix.'.bulk'),
            'paginator' => $records,
            'noun' => 'elementi',
            'nounOne' => 'elemento',
            'actions' => ['delete' => 'Elimina'],
            'onlySelected' => ['delete'],
        ])
    @endif

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead>
            <tr>
                @if ($bulk)
                    <th class="ksm-bulk__cell"><input type="checkbox" data-bulk-page aria-label="Seleziona tutti in questa pagina"></th>
                @endif
                @foreach ($columns as $column => $label)
                    <th>{{ $label }}</th>
                @endforeach
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($records as $record)
                <tr>
                    @if ($bulk)
                        <td class="ksm-bulk__cell">
                            <input type="checkbox" name="ids[]" value="{{ $record->getKey() }}" form="bulk" data-bulk-item
                                   aria-label="Seleziona {{ $record->{array_key_first($columns)} }}">
                        </td>
                    @endif
                    @foreach ($columns as $column => $label)
                        <td>
                            @if (is_bool($record->$column))
                                <span class="ksm-badge @if (! $record->$column) ksm-badge--muted @endif">
                                    {{ $record->$column ? 'Sì' : 'No' }}
                                </span>
                            @elseif ($loop->first)
                                <a href="{{ route($routePrefix.'.edit', $record) }}" style="font-weight: 600; color: var(--ksm-ink);">{{ $record->$column }}</a>
                            @else
                                {{ $record->$column }}
                            @endif
                        </td>
                    @endforeach
                    <td>
                        <div class="ksm-rowactions" style="flex-wrap: nowrap;">
                            @foreach ($rowActions as [$actionLabel, $actionRoute, $actionMethod])
                                <form method="POST" action="{{ route($actionRoute, $record) }}">
                                    @csrf @method($actionMethod)
                                    <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">{{ $actionLabel }}</button>
                                </form>
                            @endforeach
                            <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route($routePrefix.'.edit', $record) }}">Modifica</a>
                            <form method="POST" action="{{ route($routePrefix.'.destroy', $record) }}"
                                  onsubmit="return confirm('Eliminare questo elemento?');">
                                @csrf @method('DELETE')
                                <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Elimina</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + ($bulk ? 2 : 1) }}" class="ksm-muted">Nessun elemento.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $records->links() }}</div>
@endsection
