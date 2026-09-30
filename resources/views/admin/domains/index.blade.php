@extends('layouts.panel')

@section('title', 'Domini · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    @php
        $canHosting = auth()->user()->can(\App\Support\Permissions::SETTINGS_MANAGE);
        $filterKeys = ['cerca', 'stato', 'tipo', 'attivo', 'ordina', 'per_pagina'];
        // Lo stesso elenco con uno stato diverso, per i contatori cliccabili.
        $stateUrl = fn (?string $state) => route('admin.domains.index', array_filter(
            array_merge(request()->only($filterKeys), ['stato' => $state, 'page' => null]),
            fn ($value) => filled($value)
        ));
        $badge = fn ($domain) => match (true) {
            $domain->domain_checked_at === null => 'ksm-badge--pending',
            $domain->dns_verified_at === null => 'ksm-badge--cancelled',
            $domain->ssl_verified_at === null => 'ksm-badge--pending',
            default => 'ksm-badge--completed',
        };
    @endphp

    <div class="ksm-listhead">
        <h1>Domini <span class="ksm-listhead__count">{{ number_format($records->total(), 0, ',', '.') }}</span></h1>

        <form method="GET" class="ksm-listhead__filters" role="search">
            <span class="ksm-listhead__search">
                <x-icon name="search" :size="16" />
                <input class="ksm-input" type="search" name="cerca" value="{{ request('cerca') }}" placeholder="Nome o dominio" aria-label="Nome o dominio">
            </span>
            <select class="ksm-select" name="stato" aria-label="Collegamento" onchange="this.form.submit()">
                <option value="">Tutti gli stati</option>
                @foreach ($states as $value => $label)
                    <option value="{{ $value }}" @selected(request('stato') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="ksm-select" name="tipo" aria-label="Tipo" onchange="this.form.submit()">
                <option value="">Tutti i tipi</option>
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected(request('tipo') === $type)>{{ $type }}</option>
                @endforeach
            </select>
            <select class="ksm-select" name="attivo" aria-label="Attivo" onchange="this.form.submit()">
                <option value="">Attivi e spenti</option>
                <option value="1" @selected(request('attivo') === '1')>Solo attivi</option>
                <option value="0" @selected(request('attivo') === '0')>Solo spenti</option>
            </select>
            <select class="ksm-select" name="ordina" aria-label="Ordina" onchange="this.form.submit()">
                @foreach ($sorts as $value => $label)
                    <option value="{{ $value }}" @selected(request('ordina', 'recenti') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="ksm-select" name="per_pagina" aria-label="Per pagina" onchange="this.form.submit()">
                @foreach ($perPageOptions as $size)
                    <option value="{{ $size }}" @selected((int) request('per_pagina', 50) === $size)>{{ $size }} per pagina</option>
                @endforeach
            </select>
            <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Cerca</button>
            @if (request()->hasAny(['cerca', 'stato', 'tipo', 'attivo', 'ordina', 'per_pagina']))
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.domains.index') }}">Azzera</a>
            @endif
        </form>

        <span class="ksm-listhead__actions">
            @if ($canHosting)
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.domains.hosting') }}"><x-icon name="settings" :size="16" /> Server e hosting</a>
            @endif
            <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route('admin.domains.create') }}">Nuovo</a>
        </span>
    </div>

    {{-- Quanti domini per stato: un clic filtra l'elenco. --}}
    <div class="ksm-domain-states">
        <a href="{{ $stateUrl(null) }}" @class(['ksm-domain-state', 'is-current' => ! request('stato')])>Tutti</a>
        @foreach ($states as $value => $label)
            <a href="{{ $stateUrl($value) }}" @class(['ksm-domain-state', 'ksm-domain-state--'.$value, 'is-current' => request('stato') === $value])>
                {{ $label }} <strong>{{ number_format($counts[$value], 0, ',', '.') }}</strong>
            </a>
        @endforeach
        <span class="ksm-domain-states__server ksm-muted">
            {{ $hosting['panel'] }} · IP {{ implode(', ', $hosting['ips']) ?: 'non impostato' }}@if ($hosting['cname']) · CNAME {{ $hosting['cname'] }}@endif
        </span>
    </div>

    @if ($records->isNotEmpty())
        @include('partials.bulk-bar', [
            'action' => route('admin.domains.bulk'),
            'paginator' => $records,
            'noun' => 'domini',
            'nounOne' => 'dominio',
            'actions' => [
                'reconnect' => 'Ricollega (pannello e verifica)',
                'verify' => 'Verifica DNS e certificato',
                'activate' => 'Attiva',
                'deactivate' => 'Spegni',
                'delete' => 'Elimina (anche dal server)',
            ],
            'onlySelected' => ['delete'],
        ])
    @endif

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead>
            <tr>
                <th class="ksm-bulk__cell"><input type="checkbox" data-bulk-page aria-label="Seleziona tutti in questa pagina"></th>
                <th>Nome</th>
                <th>Dominio</th>
                <th>Tipo</th>
                <th>Attivo</th>
                <th>Collegamento</th>
                <th>Ultima verifica</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($records as $domain)
                <tr>
                    <td class="ksm-bulk__cell">
                        <input type="checkbox" name="ids[]" value="{{ $domain->id }}" form="bulk" data-bulk-item aria-label="Seleziona {{ $domain->domain }}">
                    </td>
                    <td><a href="{{ route('admin.domains.edit', $domain) }}" style="font-weight: 600; color: var(--ksm-ink);">{{ $domain->name }}</a></td>
                    <td><a href="https://{{ $domain->domain }}" target="_blank" rel="noopener">{{ $domain->domain }}</a></td>
                    <td>{{ $domain->type }}</td>
                    <td><span class="ksm-badge @unless ($domain->is_active) ksm-badge--muted @endunless">{{ $domain->is_active ? 'Sì' : 'No' }}</span></td>
                    <td>
                        <span class="ksm-badge {{ $badge($domain) }}">{{ $domain->connectionLabel() }}</span>
                        @if (filled($domain->domain_error))
                            <small class="ksm-domain-error" title="{{ $domain->domain_error }}">{{ \Illuminate\Support\Str::limit($domain->domain_error, 70) }}</small>
                        @endif
                    </td>
                    <td class="ksm-muted" style="white-space: nowrap;">{{ $domain->domain_checked_at?->diffForHumans() ?? 'Mai' }}</td>
                    <td>
                        <div class="ksm-rowactions" style="flex-wrap: nowrap;">
                            <form method="POST" action="{{ route('admin.domains.check', $domain) }}">
                                @csrf @method('PATCH')
                                <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Verifica ora</button>
                            </form>
                            <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route('admin.domains.edit', $domain) }}">Modifica</a>
                            <form method="POST" action="{{ route('admin.domains.destroy', $domain) }}"
                                  onsubmit="return confirm('Eliminare {{ $domain->domain }}? Viene tolto anche dal server.');">
                                @csrf @method('DELETE')
                                <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Elimina</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="ksm-muted">Nessun dominio.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $records->links() }}</div>

    <style>
        .ksm-domain-states { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 14px; }
        .ksm-domain-state { display: inline-flex; gap: 6px; align-items: center; padding: 5px 12px; border-radius: var(--ksm-radius-pill); background: var(--ksm-line-soft); color: var(--ksm-body); font-size: .85rem; text-decoration: none; }
        .ksm-domain-state strong { color: var(--ksm-ink); }
        .ksm-domain-state.is-current { background: var(--ksm-accent-soft); color: var(--ksm-accent-dark); font-weight: 600; }
        .ksm-domain-state--errore strong, .ksm-domain-state--dns strong { color: var(--ksm-danger); }
        .ksm-domain-states__server { margin-left: auto; font-size: .82rem; }
        .ksm-domain-error { display: block; margin-top: 4px; color: var(--ksm-danger); font-size: .78rem; max-width: 320px; }
    </style>
@endsection
