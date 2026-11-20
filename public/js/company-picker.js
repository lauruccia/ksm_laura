// Scelta di un'azienda con ricerca (vedi components/company-picker.blade.php).
//
// Scrivendo almeno due lettere chiede al server le aziende che contengono il testo
// e le mostra sotto il campo. Scelta una, l'id va nel campo nascosto; se invece si
// lascia il testo, parte il testo come nome. Senza script resta un campo di testo.
(() => {
    document.querySelectorAll('[data-company-picker]').forEach((root, n) => {
        const idInput = root.querySelector('[data-cpick-id]');
        const text = root.querySelector('[data-cpick-text]');
        const list = root.querySelector('[data-cpick-list]');
        const textName = root.dataset.textName;
        const submitOnPick = root.hasAttribute('data-submit');
        const listId = `cpick-list-${n}`;

        list.id = listId;
        text.setAttribute('aria-controls', listId);

        let timer = null;
        let controller = null;
        let items = [];
        let active = -1;

        const close = () => {
            list.hidden = true;
            text.setAttribute('aria-expanded', 'false');
            text.removeAttribute('aria-activedescendant');
            active = -1;
        };

        const mark = index => {
            active = index;
            [...list.querySelectorAll('[role="option"]')].forEach((li, i) => {
                li.classList.toggle('is-active', i === active);
                li.setAttribute('aria-selected', i === active ? 'true' : 'false');
                if (i === active) {
                    text.setAttribute('aria-activedescendant', li.id);
                    li.scrollIntoView({ block: 'nearest' });
                }
            });
        };

        const render = () => {
            list.replaceChildren();

            if (items.length === 0) {
                const empty = document.createElement('li');
                empty.className = 'ksm-cpick__empty';
                empty.textContent = 'Nessuna azienda trovata';
                list.append(empty);
            }

            items.forEach((company, i) => {
                const li = document.createElement('li');
                li.id = `${listId}-${i}`;
                li.className = 'ksm-cpick__option';
                li.setAttribute('role', 'option');
                li.dataset.index = String(i);
                li.textContent = company.name;
                if (company.city) {
                    const small = document.createElement('small');
                    small.textContent = ` · ${company.city}`;
                    li.append(small);
                }
                list.append(li);
            });

            list.hidden = false;
            text.setAttribute('aria-expanded', 'true');
            active = -1;
        };

        const choose = company => {
            idInput.value = company.id;
            text.value = company.name;
            text.removeAttribute('name');
            close();

            const form = root.closest('form');
            if (submitOnPick && form) {
                form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        };

        const search = query => {
            if (controller) controller.abort();
            controller = new AbortController();

            fetch(`${root.dataset.url}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then(response => (response.ok ? response.json() : []))
                .then(data => {
                    items = Array.isArray(data) ? data : [];
                    render();
                })
                .catch(error => {
                    if (error.name !== 'AbortError') close();
                });
        };

        text.addEventListener('input', () => {
            // Si sta riscrivendo: la scelta di prima non vale piu'.
            idInput.value = '';
            text.name = textName;

            clearTimeout(timer);
            const query = text.value.trim();
            if (query.length < 2) {
                close();
                return;
            }
            timer = setTimeout(() => search(query), 200);
        });

        text.addEventListener('keydown', event => {
            const open = !list.hidden && items.length > 0;

            if (event.key === 'ArrowDown' && open) {
                event.preventDefault();
                mark(Math.min(active + 1, items.length - 1));
            } else if (event.key === 'ArrowUp' && open) {
                event.preventDefault();
                mark(Math.max(active - 1, 0));
            } else if (event.key === 'Enter') {
                if (open && active >= 0) {
                    event.preventDefault();
                    choose(items[active]);
                } else if (!submitOnPick) {
                    // Dentro un modulo che fa altro (azioni in blocco): Invio non deve applicarle.
                    event.preventDefault();
                }
            } else if (event.key === 'Escape') {
                close();
            }
        });

        // mousedown e non click: il campo non deve perdere il focus prima della scelta.
        list.addEventListener('mousedown', event => {
            event.preventDefault();
            const li = event.target.closest('[role="option"]');
            if (li) choose(items[Number(li.dataset.index)]);
        });

        text.addEventListener('blur', () => setTimeout(close, 120));
    });
})();
