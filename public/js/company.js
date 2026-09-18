// Minisito dell'azienda: voce corrente nel menu interno e galleria a schermo intero.
// Senza JavaScript il menu resta un elenco di ancore e la galleria apre l'immagine.

const minisite = document.querySelector('[data-minisite]');

if (minisite) {
    const nav = minisite.querySelector('[data-minisite-nav]');
    const links = nav ? [...nav.querySelectorAll('a[href^="#"]')] : [];

    // La voce corrente e' quella dell'ultima sezione che ha passato il menu.
    if (links.length && 'IntersectionObserver' in window) {
        const sections = links
            .map((link) => document.getElementById(link.getAttribute('href').slice(1)))
            .filter(Boolean);

        const mark = (id) => links.forEach((link) => {
            link.classList.toggle('is-current', link.getAttribute('href') === `#${id}`);
        });

        const seen = new Set();

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => entry.isIntersecting ? seen.add(entry.target.id) : seen.delete(entry.target.id));

            const current = sections.find((section) => seen.has(section.id));

            if (current) {
                mark(current.id);
            }
        }, { rootMargin: '-72px 0px -70% 0px' });

        sections.forEach((section) => observer.observe(section));
    }

    // Galleria: l'immagine si apre grande, si chiude con Esc o cliccando fuori.
    const gallery = minisite.querySelector('[data-gallery]');

    if (gallery && typeof HTMLDialogElement === 'function') {
        const dialog = document.createElement('dialog');
        dialog.className = 'ksm-lightbox';
        dialog.innerHTML = '<button class="ksm-lightbox__close" type="button" aria-label="Chiudi">&times;</button><img alt="">';
        minisite.append(dialog);

        const image = dialog.querySelector('img');

        gallery.addEventListener('click', (event) => {
            const link = event.target.closest('a');

            if (! link) {
                return;
            }

            event.preventDefault();
            image.src = link.getAttribute('href');
            image.alt = link.querySelector('img')?.alt || '';
            dialog.showModal();
        });

        dialog.addEventListener('click', (event) => {
            // Fuori dall'immagine si chiude: il click arriva al dialog stesso.
            if (event.target === dialog || event.target.closest('.ksm-lightbox__close')) {
                dialog.close();
            }
        });
    }
}
