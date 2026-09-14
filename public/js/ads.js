// Circuito banner: conta una visualizzazione quando il banner resta sullo
// schermo almeno un secondo, e apre il popup al massimo una volta al giorno.
(() => {
    // Un browser pilotato da un programma lo dichiara: niente conti e niente popup.
    if (navigator.webdriver) {
        return;
    }

    // Lo standard di settore: meta' del banner visibile per un secondo intero.
    const VISIBLE_MS = 1000;
    const visible = new Set();
    const timers = new Map();

    const count = (element) => {
        if (element.dataset.adCounted) {
            return;
        }

        element.dataset.adCounted = '1';

        if (navigator.sendBeacon) {
            navigator.sendBeacon(element.dataset.adView);
        } else {
            fetch(element.dataset.adView, { method: 'POST', keepalive: true });
        }
    };

    const disarm = (element) => {
        clearTimeout(timers.get(element));
        timers.delete(element);
    };

    const arm = (element) => {
        if (element.dataset.adCounted || timers.has(element) || document.visibilityState !== 'visible') {
            return;
        }

        timers.set(element, setTimeout(() => {
            timers.delete(element);

            if (visible.has(element) && document.visibilityState === 'visible') {
                count(element);
            }
        }, VISIBLE_MS));
    };

    const watchSlots = () => {
        // Senza IntersectionObserver non si sa se il banner e' stato visto: meglio non contare.
        if (!('IntersectionObserver' in window)) {
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    visible.add(entry.target);
                    arm(entry.target);
                } else {
                    visible.delete(entry.target);
                    disarm(entry.target);
                }
            });
        }, { threshold: 0.5 });

        document.querySelectorAll('[data-ad-view]:not([data-ad-popup])').forEach((slot) => observer.observe(slot));

        // Una scheda in secondo piano non guarda niente.
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                visible.forEach(arm);
            } else {
                [...timers.keys()].forEach(disarm);
            }
        });
    };

    const openPopup = () => {
        const popup = document.querySelector('[data-ad-popup]');

        if (!popup || typeof popup.showModal !== 'function') {
            return;
        }

        const key = `ksm-ad-popup-${popup.dataset.adPopup}`;
        const today = new Date().toISOString().slice(0, 10);

        try {
            if (localStorage.getItem(key) === today) {
                return;
            }
        } catch (error) {
            // Senza memoria del browser il popup si mostra comunque, una volta per pagina.
        }

        setTimeout(() => {
            if (document.visibilityState !== 'visible') {
                return;
            }

            popup.showModal();

            try {
                localStorage.setItem(key, today);
            } catch (error) {
                // Vedi sopra.
            }

            setTimeout(() => {
                if (popup.open) {
                    count(popup);
                }
            }, VISIBLE_MS);
        }, 4000);

        // Un clic fuori dall'immagine chiude.
        popup.addEventListener('click', (event) => {
            if (event.target === popup) {
                popup.close();
            }
        });
    };

    const start = () => {
        watchSlots();
        openPopup();
    };

    // Una pagina preparata in anticipo dal browser non e' ancora stata vista.
    if (document.prerendering) {
        document.addEventListener('prerenderingchange', start, { once: true });
    } else {
        start();
    }
})();
