@extends('layouts.panel')

@section('title', 'Domini · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    @php
        $canHosting = auth()->user()->can(\App\Support\Permissions::SETTINGS_MANAGE);
        $filterKeys = ['cerca', 'stato', 'tipo', 'attivo', 'errore', 'ordina', 'per_pagina'];
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

        <span class="ksm-listhead__actions" style="margin-left: auto;">
            @if ($canHosting)
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.domains.hosting') }}"><x-icon name="settings" :size="16" /> Server e hosting</a>
            @endif
            <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route('admin.domains.create') }}">Nuovo</a>
        </span>
    </div>

    {{-- I filtri su una riga loro, sotto il titolo: con sei scelte la testata non basta. --}}
    <form method="GET" class="ksm-listhead__filters ksm-domain-toolbar" role="search">
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
                    <option value="{{ $size }}" @selected((int) request('per_pagina', 50) === $size)>{{ $size }} / pag.</option>
                @endforeach
            </select>
            <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Cerca</button>
            @if (request()->hasAny(['cerca', 'stato', 'tipo', 'attivo', 'ordina', 'per_pagina']))
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.domains.index') }}">Azzera</a>
            @endif
        </form>

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

    {{-- Lo stesso errore su tanti domini si legge una volta sola, con un clic per filtrarli (e poi Ricollegarli). --}}
    @if ($topErrors->isNotEmpty())
        <div class="ksm-domain-errors">
            <strong>Errori più frequenti</strong>
            <ul>
                @foreach ($topErrors as $error)
                    <li>
                        <a href="{{ route('admin.domains.index', array_filter(array_merge(request()->only($filterKeys), ['errore' => $error->domain_error, 'stato' => null]), fn ($v) => filled($v))) }}">
                            <span class="ksm-domain-errors__count">{{ number_format($error->total, 0, ',', '.') }}</span>
                            <span>{{ $error->domain_error }}</span>
                        </a>
                        @include('admin.domains._error-help', ['error' => $error->domain_error])
                    </li>
                @endforeach
            </ul>
            @if (request()->filled('errore'))
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.domains.index', request()->except(['errore', 'page'])) }}">Mostra tutti gli errori</a>
            @endif
        </div>
    @endif

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
        <table class="ksm-table ksm-domains-table">
            <thead>
            <tr>
                <th class="ksm-bulk__cell"><input type="checkbox" data-bulk-page aria-label="Seleziona tutti in questa pagina"></th>
                <th>Dominio</th>
                <th>Collegamento</th>
                <th>Verificato</th>
                <th class="ksm-domains-table__actions"></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($records as $domain)
                <tr @class(['is-off' => ! $domain->is_active])>
                    <td class="ksm-bulk__cell">
                        <input type="checkbox" name="ids[]" value="{{ $domain->id }}" form="bulk" data-bulk-item aria-label="Seleziona {{ $domain->domain }}">
                    </td>
                    <td>
                        <div class="ksm-domains-table__host">
                            <a href="{{ route('admin.domains.edit', $domain) }}">{{ $domain->domain }}</a>
                            <a class="ksm-domains-table__open" href="https://{{ $domain->domain }}" target="_blank" rel="noopener" title="Apri il sito" aria-label="Apri {{ $domain->domain }}">↗</a>
                            {{-- Controlli esterni, in una scheda nuova: chi è registrato e dove punta il DNS nel mondo. --}}
                            <a class="ksm-domains-table__tool" href="https://www.whois.com/whois/{{ $domain->domain }}" target="_blank" rel="noopener" title="WHOIS: registrar, scadenza e nameserver">WHOIS</a>
                            <a class="ksm-domains-table__tool" href="https://dnschecker.org/#A/{{ $domain->domain }}" target="_blank" rel="noopener" title="DNS Checker: a quale IP punta il dominio nel mondo">DNS</a>
                        </div>
                        <div class="ksm-domains-table__meta">
                            @if ($domain->name !== $domain->domain){{ $domain->name }} · @endif{{ $domain->type }}
                            @unless ($domain->is_active)<span class="ksm-badge ksm-badge--muted">Spento</span>@endunless
                        </div>
                    </td>
                    <td class="ksm-domains-table__state">
                        <span class="ksm-badge {{ $badge($domain) }}">{{ $domain->connectionLabel() }}</span>
                        @if (filled($domain->domain_error))
                            <span class="ksm-domains-table__errline">
                                <span class="ksm-domains-table__error" title="{{ $domain->domain_error }}">{{ $domain->domain_error }}</span>
                                @include('admin.domains._error-help', ['error' => $domain->domain_error])
                            </span>
                        @endif
                    </td>
                    <td class="ksm-muted ksm-domains-table__when" title="{{ $domain->domain_checked_at?->format('d/m/Y H:i') }}">{{ $domain->domain_checked_at?->locale('it')->diffForHumans(short: true) ?? 'Mai' }}</td>
                    <td class="ksm-domains-table__actions">
                        <div class="ksm-rowactions">
                            <form method="POST" action="{{ route('admin.domains.check', $domain) }}">
                                @csrf @method('PATCH')
                                <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Verifica</button>
                            </form>
                            <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route('admin.domains.edit', $domain) }}">Modifica</a>
                            <form method="POST" action="{{ route('admin.domains.destroy', $domain) }}"
                                  onsubmit="return confirm('Eliminare {{ $domain->domain }}? Viene tolto anche dal server.');">
                                @csrf @method('DELETE')
                                <button class="ksm-btn ksm-btn--ghost ksm-btn--sm ksm-domains-table__delete" type="submit" title="Elimina" aria-label="Elimina {{ $domain->domain }}">Elimina</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="ksm-muted">Nessun dominio.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $records->links() }}</div>

    <style>
        .ksm-domain-toolbar { margin: 0 0 12px; width: 100%; }
        .ksm-domain-toolbar .ksm-listhead__search { flex: 1 1 200px; }
        .ksm-panel__main .ksm-domain-toolbar .ksm-listhead__search .ksm-input { width: 100%; }
        .ksm-domain-states { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 14px; }
        .ksm-domain-state { display: inline-flex; gap: 6px; align-items: center; padding: 5px 12px; border-radius: var(--ksm-radius-pill); background: var(--ksm-line-soft); color: var(--ksm-body); font-size: .85rem; text-decoration: none; white-space: nowrap; }
        .ksm-domain-state strong { color: var(--ksm-ink); }
        .ksm-domain-state.is-current { background: var(--ksm-accent-soft); color: var(--ksm-accent-dark); font-weight: 600; }
        .ksm-domain-state--errore strong, .ksm-domain-state--dns strong { color: var(--ksm-danger); }
        .ksm-domain-states__server { margin-left: auto; font-size: .82rem; }

        .ksm-domain-errors { margin-bottom: 14px; padding: 12px 16px; border-radius: var(--ksm-radius-lg); background: #FDECEC; color: var(--ksm-danger); font-size: .88rem; }
        .ksm-domain-errors ul { list-style: none; margin: 6px 0 0; padding: 0; display: grid; gap: 4px; }
        .ksm-domain-errors li { display: flex; gap: 8px; align-items: center; }
        .ksm-domain-errors a { display: flex; gap: 10px; align-items: baseline; color: inherit; text-decoration: none; }
        .ksm-domain-errors a:hover span:last-child { text-decoration: underline; }
        .ksm-domain-errors__count { flex: none; min-width: 34px; padding: 1px 8px; border-radius: var(--ksm-radius-pill); background: #fff; font-weight: 700; text-align: center; }
        .ksm-domain-errors .ksm-btn { margin-top: 8px; }

        .ksm-domains-table { min-width: 720px; }
        .ksm-domains-table td { vertical-align: middle; padding-block: 12px; }
        .ksm-domains-table tr.is-off td:not(.ksm-bulk__cell):not(.ksm-domains-table__actions) { opacity: .6; }
        .ksm-domains-table__host { display: flex; align-items: center; gap: 6px; }
        .ksm-domains-table__host a:first-child { font-weight: 600; color: var(--ksm-ink); }
        .ksm-domains-table__open { color: var(--ksm-muted); text-decoration: none; font-size: .9rem; }
        .ksm-domains-table__tool { padding: 0 6px; border: 1px solid var(--ksm-line-soft); border-radius: var(--ksm-radius-pill); color: var(--ksm-muted); font-size: .68rem; font-weight: 600; letter-spacing: .03em; text-decoration: none; }
        .ksm-domains-table__tool:hover { color: var(--ksm-accent-dark); border-color: var(--ksm-accent-soft); background: var(--ksm-accent-soft); }
        .ksm-domains-table__meta { margin-top: 2px; color: var(--ksm-muted); font-size: .8rem; }
        .ksm-domains-table__meta .ksm-badge { margin-left: 4px; padding: 1px 8px; font-size: .72rem; display: inline; }
        .ksm-domains-table__state { width: auto; }
        .ksm-domains-table__state .ksm-badge { white-space: nowrap; }
        /* L'errore su una riga sola: intero passando sopra con il mouse; il "?" accanto dice come risolverlo. */
        .ksm-domains-table__errline { display: flex; align-items: center; gap: 6px; margin-top: 4px; }
        .ksm-domains-table__error { display: block; max-width: 340px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; color: var(--ksm-danger); font-size: .78rem; cursor: help; }
        .ksm-domains-table__when { white-space: nowrap; font-size: .85rem; }
        .ksm-domains-table__actions { width: 1%; white-space: nowrap; }
        .ksm-domains-table__actions .ksm-rowactions { flex-wrap: nowrap; justify-content: flex-end; }
    </style>
@endsection
