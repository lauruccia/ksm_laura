// Apertura del menu sotto i 980px e della ricerca nella barra.
document.addEventListener('click', (event) => {
    const navToggle = event.target.closest('[data-nav-toggle]');

    if (navToggle) {
        const header = navToggle.closest('[data-site-header]');
        const open = header.classList.toggle('is-open');
        navToggle.setAttribute('aria-expanded', String(open));
        return;
    }

    const searchToggle = event.target.closest('[data-search-toggle]');

    if (searchToggle) {
        const panel = document.getElementById('ksm-navsearch');
        const open = panel.classList.toggle('is-open');
        searchToggle.setAttribute('aria-expanded', String(open));

        if (open) {
            panel.querySelector('input')?.focus();
        }
    }
});

// Ombra della barra quando la pagina scorre.
const header = document.querySelector('[data-site-header]');

if (header) {
    const updateHeader = () => header.classList.toggle('is-stuck', window.scrollY > 8);

    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });
}

// Comparsa morbida dei blocchi principali.
const reveals = document.querySelectorAll('.ksm-reveal');

if (reveals.length && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -8% 0px' });

    reveals.forEach((el) => observer.observe(el));
} else {
    reveals.forEach((el) => el.classList.add('is-visible'));
}

// Catalogo: colonne della griglia, pannello dei filtri, ordinamento immediato.
const shop = document.querySelector('[data-shop]');

if (shop) {
    const grid = shop.querySelector('[data-shop-grid]');
    const colButtons = shop.querySelectorAll('[data-shop-cols]');

    const syncCols = () => colButtons.forEach((button) => {
        button.setAttribute('aria-pressed', String(button.dataset.shopCols === grid?.dataset.cols));
    });

    const setFiltersOpen = (open) => {
        shop.classList.toggle('is-filters-open', open);
        document.body.classList.toggle('ksm-no-scroll', open);
        shop.querySelectorAll('[aria-controls="ksm-shop-side"]').forEach((button) => {
            button.setAttribute('aria-expanded', String(open));
        });
        if (open) {
            shop.querySelector('.ksm-shop__close')?.focus();
        } else {
            shop.querySelector('[aria-controls="ksm-shop-side"]')?.focus();
        }
    };

    syncCols();

    shop.addEventListener('click', (event) => {
        const colsButton = event.target.closest('[data-shop-cols]');

        if (colsButton && grid) {
            grid.dataset.cols = colsButton.dataset.shopCols;
            syncCols();

            try {
                localStorage.setItem('ksm.shop.cols', colsButton.dataset.shopCols);
            } catch (e) {}

            return;
        }

        if (event.target.closest('[data-shop-filters-toggle]')) {
            setFiltersOpen(! shop.classList.contains('is-filters-open'));
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Tab' && shop.classList.contains('is-filters-open')) {
            const controls = [...shop.querySelectorAll('.ksm-shop__side a[href], .ksm-shop__side button, .ksm-shop__side input:not([type="hidden"]), .ksm-shop__side select')].filter((element) => !element.disabled);
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        }
        if (event.key === 'Escape' && shop.classList.contains('is-filters-open')) {
            setFiltersOpen(false);
        }
    });

    shop.addEventListener('change', (event) => {
        const select = event.target.closest('[data-shop-autosubmit]');

        if (select?.form) {
            select.form.requestSubmit ? select.form.requestSubmit() : select.form.submit();
        }
    });
}

// Directory aziende: le pagine successive arrivano man mano che si scorre.
// Il server risponde con le sole schede; senza JavaScript resta la paginazione.
const directoryGrid = document.querySelector('[data-directory-grid]');
const directoryMore = document.querySelector('[data-directory-more]');

if (directoryGrid && directoryMore && 'IntersectionObserver' in window) {
    // Il link alla pagina successiva viaggia in fondo alle schede.
    const takeNext = (root) => {
        const link = root.querySelector('[data-directory-next]');
        link?.remove();

        return link?.getAttribute('href') || null;
    };

    let next = takeNext(directoryGrid);
    let loading = false;

    const observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
            loadNext();
        }
    }, { rootMargin: '0px 0px 900px 0px' });

    const loadNext = async () => {
        if (loading || !next) {
            return;
        }

        loading = true;
        directoryMore.classList.remove('is-failed');
        directoryMore.classList.add('is-loading');

        try {
            const response = await fetch(next, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const template = document.createElement('template');
            template.innerHTML = await response.text();
            next = takeNext(template.content);
            directoryGrid.append(template.content);
        } catch (error) {
            // Resta la stessa pagina da chiedere: il bottone riprova.
            directoryMore.classList.add('is-failed');
        } finally {
            loading = false;
            directoryMore.classList.remove('is-loading');
        }

        // Osservare di nuovo fa ripartire il controllo: se il fondo e' ancora
        // in vista, per esempio su uno schermo alto, arriva subito un'altra pagina.
        observer.unobserve(directoryMore);

        if (next && !directoryMore.classList.contains('is-failed')) {
            observer.observe(directoryMore);
        }
    };

    if (next) {
        directoryMore.classList.add('is-live');
        directoryMore.querySelector('[data-directory-retry]')?.addEventListener('click', loadNext);
        observer.observe(directoryMore);
    }
}
