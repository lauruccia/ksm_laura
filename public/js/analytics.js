// Statistiche delle visite: dice al sito per quanti secondi la pagina e'
// rimasta davanti agli occhi di chi la guarda. Niente cookie, niente
// memoria del browser: la pagina porta con se' una sigla casuale (meta
// ksm-pv) e la rimanda con il totale ogni volta che viene lasciata.
(() => {
    const meta = document.querySelector('meta[name="ksm-pv"]');

    // Un browser pilotato da un programma lo dichiara: non e' una persona.
    if (!meta || navigator.webdriver) {
        return;
    }

    let total = 0;          // millisecondi gia' accumulati
    let since = null;       // da quando la pagina e' visibile
    let sent = 0;           // secondi gia' comunicati

    const start = () => {
        if (since === null && document.visibilityState === 'visible') {
            since = performance.now();
        }
    };

    const stop = () => {
        if (since !== null) {
            total += performance.now() - since;
            since = null;
        }
    };

    const send = () => {
        stop();

        const seconds = Math.round(total / 1000);

        // Meno di un secondo non e' una visita, e lo stesso totale non si rimanda.
        if (seconds < 1 || seconds <= sent) {
            return;
        }

        sent = seconds;

        const body = new FormData();
        body.append('pv', meta.content);
        body.append('t', String(seconds));

        if (!navigator.sendBeacon || !navigator.sendBeacon(meta.dataset.url, body)) {
            fetch(meta.dataset.url, { method: 'POST', body, keepalive: true }).catch(() => {});
        }
    };

    const run = () => {
        start();

        // Scheda in secondo piano o chiusura: si conta quello che e' passato e si comunica.
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                send();
            } else {
                start();
            }
        });

        // Safari sui telefoni non sempre lancia visibilitychange alla chiusura.
        window.addEventListener('pagehide', send);

        // Tornando con "indietro" la pagina riparte dalla cache del browser.
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                start();
            }
        });
    };

    // Una pagina preparata in anticipo dal browser non e' ancora stata vista.
    if (document.prerendering) {
        document.addEventListener('prerenderingchange', run, { once: true });
    } else {
        run();
    }
})();
