@extends('layouts.panel')

@section('title', 'Menu · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <style>
        .ksm-menus { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(560px, 100%), 1fr)); gap: 20px; align-items: start; }
        .ksm-menus__card { padding: 22px 24px; scroll-margin-top: 20px; }
        .ksm-menus__head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 4px; }
        .ksm-menus__head h2 { margin: 0; font-size: 1.05rem; }
        .ksm-menus__badge { padding: 2px 10px; border-radius: 999px; font-size: .75rem; font-weight: 600; background: var(--ksm-line-soft); color: var(--ksm-muted); }
        .ksm-menus__badge--custom { background: var(--ksm-accent-soft); color: var(--ksm-accent-dark); }
        .ksm-menus__rows { display: grid; gap: 8px; margin: 14px 0; }
        .ksm-menus__row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.6fr) auto auto; gap: 8px; align-items: center; }
        .ksm-menus__tab { display: flex; gap: 6px; align-items: center; font-size: .85rem; white-space: nowrap; }
        .ksm-menus__tools { display: flex; gap: 2px; }
        .ksm-menus__tools button { width: 32px; height: 32px; padding: 0; border: 1px solid var(--ksm-line); border-radius: 8px; background: #fff; cursor: pointer; line-height: 1; }
        .ksm-menus__tools button:hover { background: var(--ksm-canvas); }
        .ksm-menus__actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        @media (max-width: 720px) {
            .ksm-menus__row { grid-template-columns: 1fr; padding-bottom: 10px; border-bottom: 1px solid var(--ksm-line-soft); }
        }
    </style>

    <div class="ksm-panel__head">
        <h1>Menu del sito</h1>
    </div>

    <p class="ksm-muted" style="max-width: 900px;">
        Ogni posizione ha il suo menu e si salva da sola. Finché una posizione non ha voci salvate,
        il sito mostra quelle predefinite, qui sotto già compilate come punto di partenza.
        Nel link si può scegliere una pagina dall'elenco oppure scrivere un indirizzo:
        <code>/prodotti</code>, <code>#sezione</code> o <code>https://…</code>.
        I domini della rete hanno i propri menu nella scheda del dominio.
    </p>

    <datalist id="ksm-menu-destinations">
        @foreach ($destinations as $url => $label)
            <option value="{{ $url }}">{{ $label }}</option>
        @endforeach
    </datalist>

    <div class="ksm-menus">
        @foreach ($menus as $location => $menu)
            @php
                // Dopo un errore la posizione inviata ritrova quello che si era scritto.
                $failed = old('_menu') === $location;
                $rows = array_values($failed ? (array) old('items', []) : $menu['items']);
            @endphp

            <form class="ksm-card ksm-menus__card" id="menu-{{ $location }}" method="POST"
                  action="{{ route('admin.menus.update', $location) }}" data-menu-form>
                @csrf
                @method('PUT')
                <input type="hidden" name="_menu" value="{{ $location }}">

                <div class="ksm-menus__head">
                    <h2>{{ $menu['label'] }}</h2>
                    @if ($menu['custom'])
                        <span class="ksm-menus__badge ksm-menus__badge--custom">Personalizzato</span>
                    @elseif ($menu['items'])
                        <span class="ksm-menus__badge">Voci predefinite</span>
                    @else
                        <span class="ksm-menus__badge">Vuoto: non compare</span>
                    @endif
                </div>

                @if (str_starts_with($location, 'top_'))
                    <small class="ksm-muted">Nella fascia scura sopra il menu principale. Sui telefoni queste voci vanno nel menu a tendina.</small>
                @elseif ($location === 'header_right' && ! $menu['custom'])
                    <small class="ksm-muted">Le voci predefinite comprendono le pagine segnate "Intestazione": una volta salvato il menu, le nuove pagine vanno aggiunte qui.</small>
                @elseif ($location === 'footer_pages' && ! $menu['custom'])
                    <small class="ksm-muted">Le voci predefinite comprendono le pagine segnate "Piede": una volta salvato il menu, le nuove pagine vanno aggiunte qui.</small>
                @elseif ($location === 'footer_links')
                    <small class="ksm-muted">Accedi, Registrati e Il mio account si aggiungono da soli, secondo chi visita.</small>
                @endif

                <div class="ksm-menus__rows" data-menu-rows data-max="{{ $maxItems }}">
                    @foreach ($rows as $i => $row)
                        @include('admin.menus.row', ['i' => $i, 'row' => $row])
                    @endforeach
                </div>

                @if ($failed)
                    @foreach ($errors->get('items') as $message)
                        <span class="ksm-error">{{ $message }}</span>
                    @endforeach
                    @foreach (array_unique(array_merge(...array_values($errors->get('items.*') ?: [[]]))) as $message)
                        <span class="ksm-error">{{ $message }}</span>
                    @endforeach
                @endif

                <template data-menu-template>
                    @include('admin.menus.row', ['i' => '__i__', 'row' => []])
                </template>

                <div class="ksm-menus__actions">
                    <button class="ksm-btn ksm-btn--ghost" type="button" data-menu-add>+ Aggiungi voce</button>
                    <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
                    @if ($menu['custom'])
                        <button class="ksm-btn ksm-btn--ghost" type="submit" name="reset" value="1"
                                onclick="return confirm('Tornare alle voci predefinite per questa posizione?')">
                            Ripristina predefinite
                        </button>
                    @endif
                </div>
            </form>
        @endforeach
    </div>

    <script>
        // Aggiungi, togli e sposta le righe; i nomi dei campi si rinumerano
        // a ogni cambio, cosi' l'ordine sulla pagina e' quello salvato.
        document.querySelectorAll('[data-menu-form]').forEach((form) => {
            const rows = form.querySelector('[data-menu-rows]');
            const template = form.querySelector('[data-menu-template]');
            const add = form.querySelector('[data-menu-add]');

            const renumber = () => {
                [...rows.children].forEach((row, i) => {
                    row.querySelectorAll('[name]').forEach((field) => {
                        field.name = field.name.replace(/items\[[^\]]*\]/, `items[${i}]`);
                    });
                });
                add.disabled = rows.children.length >= Number(rows.dataset.max);
            };

            add.addEventListener('click', () => {
                rows.insertAdjacentHTML('beforeend', template.innerHTML);
                renumber();
                rows.lastElementChild.querySelector('input').focus();
            });

            rows.addEventListener('click', (event) => {
                const button = event.target.closest('[data-menu-move]');
                if (!button) return;
                const row = button.closest('.ksm-menus__row');
                const move = button.dataset.menuMove;

                if (move === 'remove') row.remove();
                if (move === 'up' && row.previousElementSibling) row.previousElementSibling.before(row);
                if (move === 'down' && row.nextElementSibling) row.nextElementSibling.after(row);
                renumber();
            });

            renumber();
        });
    </script>
@endsection
