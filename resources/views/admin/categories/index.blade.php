@extends('layouts.panel')

@section('title', $title.' · KSM')
@section('role', 'Amministrazione')
@section('crumb', $title)
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-listhead">
        <h1>{{ $title }} <span class="ksm-listhead__count">{{ $stats['total'] }}</span></h1>
        <span class="ksm-muted" style="font-size: .85rem;">
            {{ $stats['roots'] }} principali e {{ $stats['children'] }} sottocategorie, fino a {{ $maxLevels }} livelli{{ $stats['empty'] ? '; '.$stats['empty'].' senza '.$itemsLabel : '' }}
        </span>
        <span class="ksm-listhead__actions" style="margin-left: auto;">
            <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route($routePrefix.'.create') }}">Nuova categoria</a>
        </span>
    </div>

    {{-- Aggiunta veloce: nome e posizione nell'albero, il resto si completa dopo. --}}
    <form class="ksm-box ksm-cattree__quick" method="POST" action="{{ route($routePrefix.'.store') }}">
        @csrf
        <div class="ksm-field">
            <label class="ksm-label" for="quick_name">Aggiungi una categoria</label>
            <input class="ksm-input" id="quick_name" name="name" placeholder="Nome della categoria" required maxlength="255">
        </div>
        <div class="ksm-field">
            <label class="ksm-label" for="quick_parent">Dentro</label>
            <select class="ksm-select" id="quick_parent" name="parent_id">
                <option value="">Nessuna: categoria principale</option>
                @foreach ($parentOptions as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="ksm-btn ksm-btn--primary" type="submit">Aggiungi</button>
    </form>

    <section class="ksm-box" data-cattree>
        <div class="ksm-cattree__bar">
            <form method="GET" class="ksm-cattree__search" role="search">
                <x-icon name="search" :size="17" />
                <input class="ksm-input" type="search" name="cerca" value="{{ $search }}"
                       placeholder="Cerca una categoria" aria-label="Cerca una categoria" data-cattree-search>
            </form>
            <div class="ksm-rowactions">
                <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="button" data-cattree-all="open">Espandi tutto</button>
                <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="button" data-cattree-all="close">Comprimi tutto</button>
            </div>
        </div>

        @if ($search !== '')
            <p class="ksm-box__hint" style="margin-top: 0;">
                Risultati per «{{ $search }}», con le categorie che li contengono ·
                <a href="{{ route($routePrefix.'.index') }}">mostra tutto l'albero</a>
            </p>
        @endif

        @if ($nodes)
            <ul class="ksm-cattree" role="tree">
                @foreach ($nodes as $node)
                    @include('admin.categories._node', ['node' => $node])
                @endforeach
            </ul>
            <p class="ksm-muted ksm-cattree__none" hidden data-cattree-none>Nessuna categoria trovata.</p>
        @else
            <p class="ksm-muted">{{ $search !== '' ? 'Nessuna categoria trovata.' : 'Ancora nessuna categoria: aggiungi la prima qui sopra.' }}</p>
        @endif
    </section>

    <script>
        // Rami chiusi all'apertura, ricerca mentre si scrive. Senza script l'albero resta tutto aperto.
        (() => {
            const root = document.querySelector('[data-cattree]');
            if (!root) return;

            const items = [...root.querySelectorAll('.ksm-cattree__item')];
            const search = root.querySelector('[data-cattree-search]');
            const none = root.querySelector('[data-cattree-none]');

            const setOpen = (item, open) => {
                const toggle = item.querySelector(':scope > .ksm-cattree__row .ksm-cattree__toggle');
                const list = item.querySelector(':scope > .ksm-cattree');
                if (!toggle || !list) return;
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                list.hidden = !open;
            };

            // Aperti solo i rami della categoria appena creata o di una ricerca.
            const target = location.hash ? document.getElementById(location.hash.slice(1)) : null;
            const searching = search && search.value.trim() !== '';
            items.forEach(item => setOpen(item, searching));
            for (let node = target?.parentElement?.closest('.ksm-cattree__item'); node; node = node.parentElement.closest('.ksm-cattree__item')) {
                setOpen(node, true);
            }
            if (target) {
                target.classList.add('is-new');
                target.scrollIntoView({ block: 'center' });
            }

            root.addEventListener('click', event => {
                const toggle = event.target.closest('.ksm-cattree__toggle');
                if (toggle) {
                    setOpen(toggle.closest('.ksm-cattree__item'), toggle.getAttribute('aria-expanded') !== 'true');
                }

                const all = event.target.closest('[data-cattree-all]');
                if (all) {
                    items.forEach(item => setOpen(item, all.dataset.cattreeAll === 'open'));
                }
            });

            // Un solo modulo "+ Sottocategoria" aperto; si chiude cliccando fuori o con Esc.
            const adds = [...root.querySelectorAll('.ksm-cattree__add')];
            const closeAdds = except => adds.forEach(add => { if (add !== except) add.open = false; });
            adds.forEach(add => add.addEventListener('toggle', () => {
                if (add.open) {
                    closeAdds(add);
                    add.querySelector('input[name="name"]')?.focus();
                }
            }));
            document.addEventListener('click', event => {
                if (!event.target.closest('.ksm-cattree__add')) closeAdds(null);
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') closeAdds(null);
            });

            search?.addEventListener('input', () => {
                const term = search.value.trim().toLowerCase();
                let shown = 0;

                items.forEach(item => item.hidden = term !== '');
                items.forEach(item => {
                    if (term === '' || !item.dataset.name.includes(term)) return;
                    shown++;
                    for (let node = item; node; node = node.parentElement.closest('.ksm-cattree__item')) {
                        node.hidden = false;
                        if (node !== item) setOpen(node, true);
                    }
                });

                if (none) none.hidden = term === '' || shown > 0;
            });
        })();
    </script>
@endsection
