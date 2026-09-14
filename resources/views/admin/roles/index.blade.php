@extends('layouts.panel')

@section('title', 'Ruoli e permessi · KSM')
@section('role', 'Amministrazione')
@section('crumb', 'Ruoli e permessi')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <div>
            <h1>Ruoli e permessi</h1>
            <p class="ksm-muted" style="margin: 4px 0 0;">
                Un ruolo e un elenco di cose concesse. Le persone si aggiungono dalla pagina Utenti.
            </p>
        </div>
        <a class="ksm-btn ksm-btn--primary" href="{{ route('admin.roles.create') }}">Nuovo ruolo</a>
    </div>

    <div class="ksm-rolegrid">
        @foreach ($roles as $role)
            <article class="ksm-box ksm-role">
                <div class="ksm-box__head">
                    <h2>{{ $role->name }}</h2>
                    @if ($role->is_system)
                        <span class="ksm-badge">di sistema</span>
                    @endif
                </div>

                @if ($role->description)
                    <p class="ksm-muted">{{ $role->description }}</p>
                @endif

                <p class="ksm-role__counts">
                    <strong>{{ $role->permissionCount() }}</strong> permessi ·
                    <strong>{{ $role->users_count }}</strong> {{ $role->users_count === 1 ? 'persona' : 'persone' }}
                </p>

                @php($labels = $role->permissionLabels())

                <ul class="ksm-taglist">
                    @foreach (array_slice($labels, 0, 6) as $label)
                        <li>{{ $label }}</li>
                    @endforeach
                    @if (count($labels) > 6)
                        <li class="ksm-taglist__more">+{{ count($labels) - 6 }}</li>
                    @endif
                </ul>

                <div class="ksm-rowactions">
                    <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.roles.edit', $role) }}">Modifica</a>

                    @unless ($role->is_system)
                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}"
                              onsubmit="return confirm('Eliminare il ruolo {{ $role->name }}?');">
                            @csrf @method('DELETE')
                            <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Elimina</button>
                        </form>
                    @endunless

                    <a class="ksm-btn ksm-btn--ghost ksm-btn--sm"
                       href="{{ route('admin.users.index', ['ruolo' => $role->id]) }}">Chi ce l ha</a>
                </div>
            </article>
        @endforeach
    </div>
@endsection
