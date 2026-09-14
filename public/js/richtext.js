// Editor per le descrizioni: grassetto, corsivo, elenchi, collegamenti.
// Sostituisce le caselle con data-richtext. Senza script resta la casella di
// testo, e in ogni caso il server ripulisce l'HTML prima di salvarlo.
(() => {
    const ALLOWED = new Set(['P', 'BR', 'B', 'STRONG', 'I', 'EM', 'U', 'UL', 'OL', 'LI', 'A', 'H2', 'H3', 'H4', 'BLOCKQUOTE']);
    const DROPPED = new Set(['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'TEMPLATE']);
    const TAG = /<\/?[a-z][a-z0-9]*(\s[^>]*)?\/?>/i;

    const BUTTONS = [
        ['bold', 'Grassetto', 'G', 'font-weight: 700;'],
        ['italic', 'Corsivo', 'C', 'font-style: italic;'],
        ['underline', 'Sottolineato', 'S', 'text-decoration: underline;'],
        ['insertUnorderedList', 'Elenco puntato', '• Elenco', ''],
        ['insertOrderedList', 'Elenco numerato', '1. Elenco', ''],
        ['createLink', 'Collegamento', 'Link', ''],
        ['removeFormat', 'Togli la formattazione', 'Pulisci', ''],
    ];

    /**
     * Copia solo la formattazione ammessa. Il documento di DOMParser non
     * esegue script ne' carica immagini: gli attributi pericolosi non
     * arrivano mai nella pagina.
     */
    const clean = (html) => {
        const source = new DOMParser().parseFromString(html, 'text/html').body;
        const holder = document.createElement('div');

        const copy = (from, to) => {
            from.childNodes.forEach((node) => {
                if (node.nodeType === Node.TEXT_NODE) {
                    to.appendChild(document.createTextNode(node.textContent));
                    return;
                }

                if (node.nodeType !== Node.ELEMENT_NODE || DROPPED.has(node.tagName)) {
                    return;
                }

                // Tag non ammesso: via il tag, resta il contenuto.
                if (!ALLOWED.has(node.tagName)) {
                    copy(node, to);
                    return;
                }

                const element = document.createElement(node.tagName);

                if (node.tagName === 'A') {
                    const href = (node.getAttribute('href') || '').trim();

                    if (/^(https?:|mailto:|tel:)/i.test(href)) {
                        element.setAttribute('href', href);
                    }
                }

                copy(node, element);
                to.appendChild(element);
            });
        };

        copy(source, holder);

        return holder.innerHTML;
    };

    const escape = (text) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    document.querySelectorAll('textarea[data-richtext]').forEach((textarea) => {
        const editor = document.createElement('div');
        editor.className = 'ksm-richtext-editor';

        const toolbar = document.createElement('div');
        toolbar.className = 'ksm-richtext-editor__toolbar';
        toolbar.setAttribute('role', 'toolbar');
        toolbar.setAttribute('aria-label', 'Formattazione');

        const area = document.createElement('div');
        area.className = 'ksm-richtext-editor__area ksm-richtext';
        area.contentEditable = 'true';
        area.setAttribute('role', 'textbox');
        area.setAttribute('aria-multiline', 'true');

        const label = document.querySelector(`label[for="${textarea.id}"]`);

        if (label) {
            area.setAttribute('aria-label', label.textContent.trim());
            label.addEventListener('click', () => area.focus());
        }

        // Le descrizioni vecchie sono testo semplice: gli a capo diventano a capo.
        area.innerHTML = TAG.test(textarea.value)
            ? clean(textarea.value)
            : escape(textarea.value).replace(/\r?\n/g, '<br>');

        const sync = () => {
            const empty = area.textContent.trim() === '' && !area.querySelector('li');
            textarea.value = empty ? '' : area.innerHTML;
        };

        BUTTONS.forEach(([command, title, text, style]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = text;
            button.title = title;
            button.setAttribute('aria-label', title);

            if (style) {
                button.style.cssText = style;
            }

            // Il clic sul pulsante non deve togliere la selezione dal testo.
            button.addEventListener('mousedown', (event) => event.preventDefault());

            button.addEventListener('click', () => {
                area.focus();

                if (command === 'createLink') {
                    const url = window.prompt('Indirizzo del collegamento', 'https://');

                    if (!url || !/^(https?:|mailto:|tel:)/i.test(url.trim())) {
                        return;
                    }

                    document.execCommand('createLink', false, url.trim());
                } else {
                    document.execCommand(command, false);
                }

                sync();
            });

            toolbar.appendChild(button);
        });

        // Incollare da Word o da una pagina web porta solo la formattazione ammessa.
        area.addEventListener('paste', (event) => {
            event.preventDefault();

            const html = event.clipboardData.getData('text/html');
            const text = event.clipboardData.getData('text/plain');

            document.execCommand('insertHTML', false, html ? clean(html) : escape(text).replace(/\r?\n/g, '<br>'));
            sync();
        });

        area.addEventListener('focus', () => {
            // A capo si va con un paragrafo, non con un <div> che il server toglierebbe.
            document.execCommand('defaultParagraphSeparator', false, 'p');
        });

        area.addEventListener('input', sync);
        textarea.form?.addEventListener('submit', sync);

        editor.append(toolbar, area);
        textarea.hidden = true;
        textarea.after(editor);
    });
})();
