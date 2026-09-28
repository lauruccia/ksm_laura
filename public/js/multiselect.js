// Scelta multipla con ricerca (componente <x-multiselect>).
// Le caselle restano le vere voci del modulo: qui si nascondono in un
// elenco a comparsa, si filtrano scrivendo e le scelte si mostrano come
// etichette con la x per toglierle.
document.querySelectorAll('[data-multiselect]').forEach((root) => {
    const box = root.querySelector('[data-ms-box]');
    const chips = root.querySelector('[data-ms-chips]');
    const search = root.querySelector('[data-ms-search]');
    const panel = root.querySelector('[data-ms-panel]');
    const tools = root.querySelector('[data-ms-tools]');
    const count = root.querySelector('[data-ms-count]');
    const empty = root.querySelector('[data-ms-empty]');
    const options = [...root.querySelectorAll('[data-ms-option]')];
    const boxOf = (option) => option.querySelector('input');
    const text = (option) => option.textContent.trim();

    root.classList.add('is-enhanced');
    box.hidden = false;
    tools.hidden = false;

    const renderChips = () => {
        chips.replaceChildren(...options.filter((option) => boxOf(option).checked).map((option) => {
            const chip = document.createElement('span');
            chip.className = 'ksm-multiselect__chip';
            chip.textContent = text(option);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.setAttribute('aria-label', 'Togli ' + text(option));
            remove.textContent = '×';
            remove.addEventListener('click', (event) => {
                event.stopPropagation();
                boxOf(option).checked = false;
                renderChips();
            });

            chip.append(remove);
            return chip;
        }));
    };

    const visible = () => options.filter((option) => !option.hidden);

    const filter = () => {
        const term = search.value.trim().toLowerCase();
        options.forEach((option) => {
            option.hidden = term !== '' && !text(option).toLowerCase().includes(term);
        });
        const shown = visible().length;
        empty.hidden = shown > 0;
        count.textContent = shown + ' di ' + options.length;
    };

    const open = () => {
        root.classList.add('is-open');
        search.setAttribute('aria-expanded', 'true');
        filter();
    };

    const close = () => {
        root.classList.remove('is-open');
        search.setAttribute('aria-expanded', 'false');
    };

    box.addEventListener('click', () => {
        search.focus();
        open();
    });
    search.addEventListener('focus', open);
    search.addEventListener('input', () => {
        open();
        filter();
    });
    search.addEventListener('keydown', (event) => {
        // Esc chiude; senza preventDefault il campo di ricerca si svuoterebbe e riaprirebbe l'elenco.
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
        // Invio sceglie il primo trovato invece di inviare il modulo, poi si cerca il prossimo.
        if (event.key === 'Enter') {
            event.preventDefault();
            const first = visible()[0];
            if (first) {
                boxOf(first).checked = !boxOf(first).checked;
                renderChips();
                search.value = '';
                filter();
            }
        }
        // Cancella a campo vuoto toglie l'ultima scelta, come nei campi a etichette.
        if (event.key === 'Backspace' && search.value === '') {
            const last = options.filter((option) => boxOf(option).checked).pop();
            if (last) {
                boxOf(last).checked = false;
                renderChips();
            }
        }
    });

    root.querySelector('[data-ms-all]').addEventListener('click', () => {
        visible().forEach((option) => { boxOf(option).checked = true; });
        renderChips();
    });
    root.querySelector('[data-ms-none]').addEventListener('click', () => {
        options.forEach((option) => { boxOf(option).checked = false; });
        renderChips();
    });

    options.forEach((option) => boxOf(option).addEventListener('change', renderChips));

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            close();
        }
    });
    root.addEventListener('focusout', (event) => {
        if (!root.contains(event.relatedTarget)) {
            close();
        }
    });

    renderChips();
    filter();
});
