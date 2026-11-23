@extends('layouts.panel')

@section('title', 'Testi pagine categoria · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-listhead">
        <h1>Testi pagine categoria <span class="ksm-listhead__count">{{ number_format($pages->total(), 0, ',', '.') }}</span></h1>
    </div>

    <p class="ksm-muted" style="max-width: 75ch; margin: 0 0 16px;">
        Sono le pagine pubbliche <strong>/categorie/…</strong> che Google può trovare: una per categoria e una per ogni categoria in una città o regione.
        Le più ricche di aziende stanno in cima, quindi conviene curare quelle per prime.
        Dove non scrivi niente, il sito usa un testo automatico.
        <strong>{{ number_format($summary['written'], 0, ',', '.') }}</strong> pagine su {{ number_format($summary['total'], 0, ',', '.') }} hanno già un testo tuo.
    </p>

    <form method="GET" class="ksm-listhead__filters" role="search" style="margin-bottom: 16px; flex-wrap: wrap;">
        <select class="ksm-select" name="categoria" aria-label="Categoria">
            <option value="">Tutte le categorie</option>
            @foreach ($categories as $id => $label)
                <option value="{{ $id }}" @selected((int) request('categoria') === $id)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="ksm-listhead__search">
            <x-icon name="search" :size="16" />
            <input class="ksm-input" type="search" name="cerca" value="{{ request('cerca') }}" placeholder="Cerca categoria o città" aria-label="Cerca">
        </span>
        <select class="ksm-select" name="stato" aria-label="Stato del testo">
            <option value="">Tutte</option>
            <option value="senza" @selected(request('stato') === 'senza')>Da scrivere (testo automatico)</option>
            <option value="con" @selected(request('stato') === 'con')>Con un testo tuo</option>
        </select>
        <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Filtra</button>
        @if (request()->hasAny(['categoria', 'cerca', 'stato']))
            <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.landing_texts.index') }}">Azzera</a>
        @endif
    </form>

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead>
            <tr>
                <th>Pagina</th>
                <th>Aziende</th>
                <th>Testo</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($pages as $page)
                @php
                    $params = ['categoria' => $page['category_id']] + ($page['place'] !== '' ? ['luogo' => $page['place']] : []);
                    $name = $page['place'] === ''
                        ? $page['category'].' · tutta Italia'
                        : $page['category'].' · '.$page['place_name'];
                @endphp
                <tr>
                    <td><a href="{{ route('admin.landing_texts.edit', $params) }}" style="font-weight: 600; color: var(--ksm-ink);">{{ $name }}</a></td>
                    <td>{{ number_format($page['count'], 0, ',', '.') }}</td>
                    <td>
                        @switch($page['status'])
                            @case('own')
                                <span class="ksm-badge">Scritto da te</span>
                                @break
                            @case('template')
                                <span class="ksm-badge">Dal modello</span>
                                @break
                            @default
                                <span class="ksm-badge ksm-badge--muted">Automatico</span>
                        @endswitch
                    </td>
                    <td>
                        <div class="ksm-rowactions" style="flex-wrap: nowrap;">
                            <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ \App\Support\CategoryLanding::url($page['slug'], $page['place']) }}" target="_blank" rel="noopener">Vedi sul sito</a>
                            <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route('admin.landing_texts.edit', $params) }}">Scrivi</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="ksm-muted">Nessuna pagina con questi filtri.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $pages->links() }}</div>
@endsection
