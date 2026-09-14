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
