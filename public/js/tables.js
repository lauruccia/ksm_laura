// Tabelle dei pannelli sul telefono: ogni cella prende il nome della sua
// colonna, cosi' il CSS puo' mostrare la riga come una scheda (vedi
// .ksm-table--stack in components.css). Le celle unite, come "Nessun
// risultato", restano senza nome e occupano tutta la larghezza.
document.querySelectorAll('.ksm-table-wrap > .ksm-table').forEach((table) => {
    const heads = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());

    if (!heads.length) {
        return;
    }

    table.querySelectorAll('tbody tr').forEach((row) => {
        let column = 0;

        [...row.children].forEach((cell) => {
            const span = cell.colSpan || 1;

            if (span === 1 && heads[column]) {
                cell.dataset.label = heads[column];
            }

            column += span;
        });
    });

    table.classList.add('ksm-table--stack');
});
