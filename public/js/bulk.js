// Selezione per le azioni in blocco (vedi partials/bulk-bar.blade.php).
//
// Tre modi: le righe spuntate a mano, tutta la pagina con la casella in
// testa alla tabella, o tutti i risultati della ricerca su ogni pagina.
// In quest'ultimo caso non si mandano id: il server rifa' la ricerca con
// gli stessi filtri (scope=all). Senza script le caselle funzionano lo stesso.
(() => {
    const form = document.querySelector('[data-bulk]');
    if (!form) return;

    const items = [...document.querySelectorAll('[data-bulk-item]')];
    const pageBox = document.querySelector('[data-bulk-page]');
    const scope = form.querySelector('[data-bulk-scope]');
    const count = form.querySelector('[data-bulk-count]');
    const action = form.querySelector('[data-bulk-action]');
    const percent = form.querySelector('[data-bulk-percent]');
    const submit = form.querySelector('[data-bulk-submit]');
    const banner = form.querySelector('[data-bulk-banner]');
    const bannerText = form.querySelector('[data-bulk-banner-text]');
    const bannerButton = form.querySelector('[data-bulk-banner-button]');
    const menu = form.querySelector('.ksm-bulk__menu');

    const total = Number(form.dataset.total);
    const noun = form.dataset.noun;
    const nounOne = form.dataset.nounOne;
    const number = n => n.toLocaleString('it-IT');
    const label = n => `${number(n)} ${n === 1 ? nounOne : noun}`;

    const checked = () => items.filter(item => item.checked).length;
    const selected = () => scope.value === 'all' ? total : checked();

    const render = () => {
        const n = selected();
        const all = checked() === items.length && items.length > 0;

        if (pageBox) {
            pageBox.checked = all;
            pageBox.indeterminate = checked() > 0 && !all;
        }

        count.textContent = n
            ? (scope.value === 'all' ? `Tutti i ${label(n)} selezionati` : `${label(n)} ${n === 1 ? 'selezionato' : 'selezionati'}`)
            : `Nessun ${nounOne} selezionato`;
        form.classList.toggle('has-selection', n > 0);
        submit.disabled = n === 0;

        items.forEach(item => item.closest('tr')?.classList.toggle('is-selected', item.checked));

        // Come nella posta: pagina intera spuntata, si propone di allargare a tutta la ricerca.
        const canWiden = all && total > items.length;
        banner.hidden = !canWiden;
        if (canWiden && scope.value === 'all') {
            bannerText.textContent = `Sono selezionati tutti i ${label(total)} della ricerca, anche nelle altre pagine.`;
            bannerButton.textContent = 'Annulla la selezione';
            bannerButton.dataset.do = 'none';
        } else if (canWiden) {
            bannerText.textContent = `Sono selezionati i ${label(items.length)} di questa pagina.`;
            bannerButton.textContent = `Seleziona tutti i ${label(total)}`;
            bannerButton.dataset.do = 'all';
        }

        if (percent) percent.hidden = action.value !== 'kmoney';
    };

    const select = mode => {
        items.forEach(item => item.checked = mode !== 'none');
        scope.value = mode === 'all' ? 'all' : 'selected';
        if (menu) menu.open = false;
        render();
    };

    items.forEach(item => item.addEventListener('change', () => {
        // Togliere una riga da "tutti i risultati" torna alla selezione a mano.
        if (!item.checked) scope.value = 'selected';
        render();
    }));

    pageBox?.addEventListener('change', () => select(pageBox.checked ? 'page' : 'none'));
    form.querySelectorAll('[data-bulk-select]').forEach(button =>
        button.addEventListener('click', () => select(button.dataset.bulkSelect)));
    bannerButton.addEventListener('click', () => select(bannerButton.dataset.do));
    action.addEventListener('change', render);

    document.addEventListener('click', event => {
        if (menu && !event.target.closest('.ksm-bulk__menu')) menu.open = false;
    });

    form.addEventListener('submit', event => {
        const n = selected();
        const chosen = action.options[action.selectedIndex].text.toLowerCase();
        const question = action.value === 'delete'
            ? `Eliminare ${label(n)}? Non si può annullare.`
            : (scope.value === 'all' ? `Applicare «${chosen}» a tutti i ${label(n)}?` : null);

        if (n === 0 || (question && !confirm(question))) {
            event.preventDefault();
        }
    });

    render();
})();
