// Ritaglio e alleggerimento delle immagini prima dell'invio.
// Si attiva sui campi file con data-image-editor:
//   data-ratios="1:1,4:3,free"  proporzioni proposte, la prima e' quella iniziale
//   data-max="1600x1600"        misura massima del file che parte
// Chi carica sposta, ingrandisce, ruota e sceglie la proporzione; il file
// scelto viene sostituito da un WebP gia' ritagliato e ridotto. Senza script
// parte il file originale e il server lo ottimizza comunque (ImageStore).
(() => {
    if (typeof DataTransfer === 'undefined' || !HTMLCanvasElement.prototype.toBlob) {
        return;
    }

    const RATIO_LABELS = { '1:1': 'Quadrato', '4:3': '4:3', '3:4': '3:4', '16:9': '16:9', '21:9': '21:9', '40:21': 'Condivisione', '3:1': '3:1', '8:1': 'Striscia', free: 'Libero' };
    const QUALITY = 0.86;
    const PAD = 22;
    const HANDLE = 16;
    const MIN_FRAME = 48;

    const kb = (bytes) => bytes >= 1048576
        ? `${(bytes / 1048576).toLocaleString('it-IT', { maximumFractionDigits: 1 })} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;

    const parseRatio = (value) => {
        if (value === 'free') {
            return null;
        }

        const [w, h] = value.split(':').map(Number);

        return w > 0 && h > 0 ? w / h : null;
    };

    const loadImage = (file) => new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => resolve({ img, url });
        img.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('unreadable'));
        };
        img.src = url;
    });

    // ------------------------------------------------------------------ editor

    let dialog;
    let ui;

    const buildDialog = () => {
        dialog = document.createElement('dialog');
        dialog.className = 'ksm-imged';
        dialog.innerHTML = `
            <form method="dialog" class="ksm-imged__box">
                <header class="ksm-imged__head">
                    <strong>Sistema l'immagine</strong>
                    <span class="ksm-imged__count"></span>
                </header>
                <div class="ksm-imged__stage">
                    <canvas aria-label="Trascina per spostare l'immagine, usa la rotellina o due dita per ingrandire"></canvas>
                </div>
                <div class="ksm-imged__tools">
                    <div class="ksm-imged__ratios" role="group" aria-label="Proporzione"></div>
                    <div class="ksm-imged__zoom">
                        <button type="button" class="ksm-imged__icon" data-act="zoom-out" aria-label="Riduci">−</button>
                        <input type="range" min="0" max="1000" value="0" aria-label="Zoom">
                        <button type="button" class="ksm-imged__icon" data-act="zoom-in" aria-label="Ingrandisci">+</button>
                    </div>
                    <div class="ksm-imged__actions">
                        <button type="button" class="ksm-imged__icon" data-act="rotate-left" aria-label="Ruota a sinistra">⟲</button>
                        <button type="button" class="ksm-imged__icon" data-act="rotate-right" aria-label="Ruota a destra">⟳</button>
                        <button type="button" class="ksm-imged__chip" data-act="center">Centra</button>
                        <label class="ksm-imged__check"><input type="checkbox" data-act="fit"> Mostra tutta l'immagine</label>
                    </div>
                </div>
                <footer class="ksm-imged__foot">
                    <small class="ksm-imged__info"></small>
                    <span class="ksm-imged__buttons">
                        <button type="button" class="ksm-btn ksm-btn--ghost ksm-btn--sm" data-act="cancel">Annulla</button>
                        <button type="button" class="ksm-btn ksm-btn--ghost ksm-btn--sm" data-act="original">Usa l'originale</button>
                        <button type="button" class="ksm-btn ksm-btn--primary ksm-btn--sm" data-act="apply">Applica</button>
                    </span>
                </footer>
            </form>`;
        document.body.appendChild(dialog);

        ui = {
            canvas: dialog.querySelector('canvas'),
            stage: dialog.querySelector('.ksm-imged__stage'),
            ratios: dialog.querySelector('.ksm-imged__ratios'),
            zoom: dialog.querySelector('input[type="range"]'),
            fit: dialog.querySelector('[data-act="fit"]'),
            count: dialog.querySelector('.ksm-imged__count'),
            info: dialog.querySelector('.ksm-imged__info'),
        };
    };

    /**
     * Apre l'editor su un file. Risolve con un File nuovo, con il file
     * originale ("Usa l'originale") o con null se si annulla.
     */
    const edit = async (file, options, counter) => {
        if (!dialog) {
            buildDialog();
        }

        let loaded;
        try {
            loaded = await loadImage(file);
        } catch {
            return file; // Formato che il browser non mostra: decide il server.
        }

        const { img, url } = loaded;
        const s = {
            iw: img.naturalWidth,
            ih: img.naturalHeight,
            rot: 0,
            scale: 1,
            cx: 0,
            cy: 0,
            frame: { x: 0, y: 0, w: 0, h: 0 },
            ratioKey: options.ratios[0],
            fit: false,
            W: 0,
            H: 0,
        };
        const ctx = ui.canvas.getContext('2d');

        const rotated = () => (s.rot % 180 === 0 ? [s.iw, s.ih] : [s.ih, s.iw]);
        const ratio = () => parseRatio(s.ratioKey);

        const minScale = () => {
            const [rw, rh] = rotated();
            const f = s.frame;

            return s.fit ? Math.min(f.w / rw, f.h / rh) : Math.max(f.w / rw, f.h / rh);
        };
        const maxScale = () => Math.max(minScale() * 10, minScale());

        const clamp = () => {
            s.scale = Math.min(Math.max(s.scale, minScale()), maxScale());
            const [rw, rh] = rotated();
            const bw = rw * s.scale;
            const bh = rh * s.scale;
            const f = s.frame;
            const lo = (start, size, box) => (box >= size ? start + size - box / 2 : start + box / 2);
            const hi = (start, size, box) => (box >= size ? start + box / 2 : start + size - box / 2);
            s.cx = Math.min(Math.max(s.cx, lo(f.x, f.w, bw)), hi(f.x, f.w, bw));
            s.cy = Math.min(Math.max(s.cy, lo(f.y, f.h, bh)), hi(f.y, f.h, bh));
        };

        /** Cornice centrata nella proporzione scelta; "Libero" parte dall'immagine intera. */
        const layout = () => {
            const aw = s.W - PAD * 2;
            const ah = s.H - PAD * 2;
            const [rw, rh] = rotated();
            const r = ratio() ?? rw / rh;
            const w = Math.min(aw, ah * r);
            const h = w / r;
            s.frame = { x: (s.W - w) / 2, y: (s.H - h) / 2, w, h };
            s.scale = 0;
            s.cx = s.W / 2;
            s.cy = s.H / 2;
            clamp();
        };

        const resize = () => {
            const rect = ui.stage.getBoundingClientRect();
            const dpr = window.devicePixelRatio || 1;
            s.W = rect.width;
            s.H = rect.height;
            ui.canvas.width = Math.round(s.W * dpr);
            ui.canvas.height = Math.round(s.H * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

            // Anche quando la finestra cambia misura si riparte dalla cornice.
            layout();
            draw();
        };

        const paintImage = (target) => {
            target.save();
            target.translate(s.cx, s.cy);
            target.rotate((s.rot * Math.PI) / 180);
            target.scale(s.scale, s.scale);
            target.imageSmoothingQuality = 'high';
            target.drawImage(img, -s.iw / 2, -s.ih / 2);
            target.restore();
        };

        const outputSize = () => {
            const srcW = s.frame.w / s.scale;
            const srcH = s.frame.h / s.scale;
            const k = Math.min(1, options.maxW / srcW, options.maxH / srcH);

            return [Math.max(1, Math.round(srcW * k)), Math.max(1, Math.round(srcH * k))];
        };

        const draw = () => {
            const f = s.frame;
            ctx.clearRect(0, 0, s.W, s.H);

            // Scacchiera nella cornice: le parti vuote restano trasparenti.
            ctx.save();
            ctx.beginPath();
            ctx.rect(f.x, f.y, f.w, f.h);
            ctx.clip();
            const cell = 10;
            for (let y = 0; y < f.h; y += cell) {
                for (let x = 0; x < f.w; x += cell) {
                    ctx.fillStyle = ((x + y) / cell) % 2 ? '#dfe6ea' : '#f6f8f9';
                    ctx.fillRect(f.x + x, f.y + y, cell, cell);
                }
            }
            ctx.restore();

            paintImage(ctx);

            ctx.fillStyle = 'rgba(7, 26, 38, .66)';
            ctx.beginPath();
            ctx.rect(0, 0, s.W, s.H);
            ctx.rect(f.x, f.y, f.w, f.h);
            ctx.fill('evenodd');

            ctx.strokeStyle = 'rgba(255, 255, 255, .35)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            for (let i = 1; i < 3; i++) {
                ctx.moveTo(f.x + (f.w * i) / 3, f.y);
                ctx.lineTo(f.x + (f.w * i) / 3, f.y + f.h);
                ctx.moveTo(f.x, f.y + (f.h * i) / 3);
                ctx.lineTo(f.x + f.w, f.y + (f.h * i) / 3);
            }
            ctx.stroke();

            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.strokeRect(f.x, f.y, f.w, f.h);

            if (!ratio()) {
                ctx.fillStyle = '#fff';
                corners().forEach(([x, y]) => ctx.fillRect(x - 6, y - 6, 12, 12));
            }

            const [ow, oh] = outputSize();
            ui.info.textContent = `Risultato: ${ow} × ${oh} px`;

            const min = minScale();
            const max = maxScale();
            ui.zoom.value = max > min ? Math.round((Math.log(s.scale / min) / Math.log(max / min)) * 1000) : 0;
        };

        const corners = () => {
            const f = s.frame;

            return [[f.x, f.y], [f.x + f.w, f.y], [f.x, f.y + f.h], [f.x + f.w, f.y + f.h]];
        };

        const zoomAt = (next, px = s.frame.x + s.frame.w / 2, py = s.frame.y + s.frame.h / 2) => {
            const old = s.scale;
            s.scale = Math.min(Math.max(next, minScale()), maxScale());
            s.cx = px - (px - s.cx) * (s.scale / old);
            s.cy = py - (py - s.cy) * (s.scale / old);
            clamp();
            draw();
        };

        // --- comandi

        ui.ratios.innerHTML = '';
        options.ratios.forEach((key) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'ksm-imged__chip';
            button.textContent = RATIO_LABELS[key] || key;
            button.setAttribute('aria-pressed', String(key === s.ratioKey));
            button.addEventListener('click', () => {
                s.ratioKey = key;
                ui.ratios.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
                layout();
                draw();
            });
            ui.ratios.appendChild(button);
        });
        ui.ratios.hidden = options.ratios.length < 2;
        ui.fit.checked = false;
        ui.count.textContent = counter;

        const pointers = new Map();
        let gesture = null;

        const point = (event) => {
            const rect = ui.canvas.getBoundingClientRect();

            return [event.clientX - rect.left, event.clientY - rect.top];
        };

        const onDown = (event) => {
            ui.canvas.setPointerCapture(event.pointerId);
            const [x, y] = point(event);
            pointers.set(event.pointerId, [x, y]);

            if (pointers.size === 2) {
                const [a, b] = [...pointers.values()];
                gesture = { type: 'pinch', dist: Math.hypot(a[0] - b[0], a[1] - b[1]), scale: s.scale };

                return;
            }

            const corner = ratio() ? -1 : corners().findIndex(([cx, cy]) => Math.abs(cx - x) < HANDLE && Math.abs(cy - y) < HANDLE);
            gesture = corner >= 0
                ? { type: 'corner', corner, frame: { ...s.frame }, x, y }
                : { type: 'pan', x, y, cx: s.cx, cy: s.cy };
        };

        const onMove = (event) => {
            const [x, y] = point(event);

            if (!pointers.has(event.pointerId)) {
                if (!ratio()) {
                    const over = corners().some(([cx, cy]) => Math.abs(cx - x) < HANDLE && Math.abs(cy - y) < HANDLE);
                    ui.canvas.style.cursor = over ? 'nwse-resize' : 'grab';
                }

                return;
            }

            pointers.set(event.pointerId, [x, y]);

            if (gesture?.type === 'pinch' && pointers.size === 2) {
                const [a, b] = [...pointers.values()];
                const dist = Math.hypot(a[0] - b[0], a[1] - b[1]);
                zoomAt(gesture.scale * (dist / gesture.dist), (a[0] + b[0]) / 2, (a[1] + b[1]) / 2);
            } else if (gesture?.type === 'pan') {
                s.cx = gesture.cx + (x - gesture.x);
                s.cy = gesture.cy + (y - gesture.y);
                clamp();
                draw();
            } else if (gesture?.type === 'corner') {
                const f = gesture.frame;
                const dx = x - gesture.x;
                const dy = y - gesture.y;
                let left = f.x;
                let top = f.y;
                let right = f.x + f.w;
                let bottom = f.y + f.h;

                if (gesture.corner % 2 === 0) left = Math.min(Math.max(PAD, left + dx), right - MIN_FRAME);
                else right = Math.max(Math.min(s.W - PAD, right + dx), left + MIN_FRAME);
                if (gesture.corner < 2) top = Math.min(Math.max(PAD, top + dy), bottom - MIN_FRAME);
                else bottom = Math.max(Math.min(s.H - PAD, bottom + dy), top + MIN_FRAME);

                s.frame = { x: left, y: top, w: right - left, h: bottom - top };
                clamp();
                draw();
            }
        };

        const onUp = (event) => {
            pointers.delete(event.pointerId);
            gesture = null;
        };

        const onWheel = (event) => {
            event.preventDefault();
            const [x, y] = point(event);
            zoomAt(s.scale * Math.exp(-event.deltaY * 0.0015), x, y);
        };

        const onClick = (event) => {
            const act = event.target.closest('[data-act]')?.dataset.act;
            const step = (maxScale() / minScale()) ** 0.1;

            if (act === 'zoom-in') zoomAt(s.scale * step);
            if (act === 'zoom-out') zoomAt(s.scale / step);
            if (act === 'rotate-left' || act === 'rotate-right') {
                s.rot = (s.rot + (act === 'rotate-right' ? 90 : 270)) % 360;
                layout();
                draw();
            }
            if (act === 'center') {
                s.cx = s.frame.x + s.frame.w / 2;
                s.cy = s.frame.y + s.frame.h / 2;
                s.scale = minScale();
                clamp();
                draw();
            }
            if (act === 'fit') {
                s.fit = ui.fit.checked;
                clamp();
                draw();
            }
            if (act === 'apply' || act === 'original' || act === 'cancel') {
                finish(act);
            }
        };

        const onRange = () => {
            const min = minScale();
            zoomAt(min * (maxScale() / min) ** (ui.zoom.value / 1000));
        };

        const onKey = (event) => {
            if (event.target === ui.zoom) {
                return;
            }

            const moves = { ArrowLeft: [-10, 0], ArrowRight: [10, 0], ArrowUp: [0, -10], ArrowDown: [0, 10] };

            if (moves[event.key]) {
                event.preventDefault();
                s.cx += moves[event.key][0];
                s.cy += moves[event.key][1];
                clamp();
                draw();
            } else if (event.key === '+' || event.key === '=') {
                zoomAt(s.scale * 1.1);
            } else if (event.key === '-') {
                zoomAt(s.scale / 1.1);
            }
        };

        /** Disegna il ritaglio alla misura finale, dimezzando a passi per non sgranare. */
        const render = () => {
            const [ow, oh] = outputSize();
            let w = Math.min(Math.round(s.frame.w / s.scale), ow * 4);
            let h = Math.round((w * oh) / ow);
            let canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            const c = canvas.getContext('2d');
            c.scale(w / s.frame.w, h / s.frame.h);
            c.translate(-s.frame.x, -s.frame.y);
            paintImage(c);

            while (w / 2 >= ow) {
                const half = document.createElement('canvas');
                half.width = Math.round(w / 2);
                half.height = Math.round(h / 2);
                const hc = half.getContext('2d');
                hc.imageSmoothingQuality = 'high';
                hc.drawImage(canvas, 0, 0, half.width, half.height);
                [canvas, w, h] = [half, half.width, half.height];
            }

            if (w !== ow || h !== oh) {
                const last = document.createElement('canvas');
                last.width = ow;
                last.height = oh;
                const lc = last.getContext('2d');
                lc.imageSmoothingQuality = 'high';
                lc.drawImage(canvas, 0, 0, ow, oh);
                canvas = last;
            }

            return canvas;
        };

        const toBlob = (canvas, type) => new Promise((resolve) => canvas.toBlob(resolve, type, QUALITY));

        const encode = async () => {
            const canvas = render();
            let blob = await toBlob(canvas, 'image/webp');

            // Browser che non scrivono WebP: PNG se puo' servire trasparenza, JPEG altrimenti.
            if (!blob || blob.type !== 'image/webp') {
                const transparent = s.fit || !/jpe?g/i.test(file.type);
                blob = await toBlob(canvas, transparent ? 'image/png' : 'image/jpeg');
            }

            const untouched = s.rot === 0 && canvas.width === s.iw && canvas.height === s.ih;

            if (untouched && blob.size >= file.size) {
                return file;
            }

            const ext = blob.type.split('/')[1].replace('jpeg', 'jpg');
            const base = file.name.replace(/\.[^.]+$/, '') || 'immagine';

            return new File([blob], `${base}.${ext}`, { type: blob.type, lastModified: Date.now() });
        };

        let settle;
        const result = new Promise((resolve) => { settle = resolve; });
        const observer = new ResizeObserver(() => resize());

        const cleanup = () => {
            observer.disconnect();
            ui.canvas.removeEventListener('pointerdown', onDown);
            ui.canvas.removeEventListener('pointermove', onMove);
            ui.canvas.removeEventListener('pointerup', onUp);
            ui.canvas.removeEventListener('pointercancel', onUp);
            ui.canvas.removeEventListener('wheel', onWheel);
            dialog.removeEventListener('click', onClick);
            dialog.removeEventListener('cancel', onCancel);
            dialog.removeEventListener('keydown', onKey);
            ui.zoom.removeEventListener('input', onRange);
            URL.revokeObjectURL(url);
            dialog.close();
        };

        let busy = false;

        const finish = async (act) => {
            if (busy) {
                return;
            }

            busy = true;

            if (act === 'apply') {
                ui.info.textContent = 'Preparo l\'immagine…';
                const out = await encode();
                cleanup();
                settle(out);
            } else {
                cleanup();
                settle(act === 'original' ? file : null);
            }
        };

        const onCancel = (event) => {
            event.preventDefault();
            finish('cancel');
        };

        ui.canvas.addEventListener('pointerdown', onDown);
        ui.canvas.addEventListener('pointermove', onMove);
        ui.canvas.addEventListener('pointerup', onUp);
        ui.canvas.addEventListener('pointercancel', onUp);
        ui.canvas.addEventListener('wheel', onWheel, { passive: false });
        dialog.addEventListener('click', onClick);
        dialog.addEventListener('cancel', onCancel);
        dialog.addEventListener('keydown', onKey);
        ui.zoom.addEventListener('input', onRange);

        dialog.showModal();
        observer.observe(ui.stage);

        return result;
    };

    // ------------------------------------------------------------ campi file

    const enhance = (input) => {
        const [maxW, maxH] = (input.dataset.max || '2000x2000').split('x').map(Number);
        const options = {
            ratios: (input.dataset.ratios || 'free').split(',').map((r) => r.trim()).filter(Boolean),
            maxW,
            maxH,
        };

        // originali e risultati, nello stesso ordine dei file del campo
        let items = [];
        const list = document.createElement('ul');
        list.className = 'ksm-imged-list';
        input.insertAdjacentElement('afterend', list);

        const sync = () => {
            const transfer = new DataTransfer();
            items.forEach((item) => transfer.items.add(item.result));
            input.files = transfer.files;
            render();
        };

        const render = () => {
            list.querySelectorAll('img').forEach((img) => URL.revokeObjectURL(img.src));
            list.innerHTML = '';

            items.forEach((item, index) => {
                const li = document.createElement('li');
                const saved = item.original.size - item.result.size;
                const img = document.createElement('img');
                img.src = URL.createObjectURL(item.result);
                img.alt = '';

                const text = document.createElement('span');
                text.className = 'ksm-imged-list__text';
                text.innerHTML = '<strong></strong><small></small>';
                text.querySelector('strong').textContent = item.result.name;
                text.querySelector('small').textContent = saved > 0
                    ? `${kb(item.original.size)} → ${kb(item.result.size)} (−${Math.round((saved / item.original.size) * 100)}%)`
                    : kb(item.result.size);

                const editButton = document.createElement('button');
                editButton.type = 'button';
                editButton.className = 'ksm-btn ksm-btn--ghost ksm-btn--sm';
                editButton.textContent = 'Modifica';
                editButton.addEventListener('click', async () => {
                    const out = await edit(item.original, options, '');
                    if (out) {
                        item.result = out;
                        sync();
                    }
                });

                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'ksm-btn ksm-btn--ghost ksm-btn--sm';
                removeButton.textContent = 'Togli';
                removeButton.addEventListener('click', () => {
                    items.splice(index, 1);
                    sync();
                });

                li.append(img, text, editButton, removeButton);
                list.appendChild(li);
            });
        };

        input.addEventListener('change', async () => {
            const files = [...input.files];
            const next = [];

            for (const [index, file] of files.entries()) {
                if (!file.type.startsWith('image/') || file.type === 'image/gif') {
                    next.push({ original: file, result: file }); // le GIF possono essere animate: restano intere
                    continue;
                }

                const out = await edit(file, options, files.length > 1 ? `${index + 1} di ${files.length}` : '');
                if (out) {
                    next.push({ original: file, result: out });
                }
            }

            items = next;
            sync();
        });
    };

    const boot = () => document.querySelectorAll('input[type="file"][data-image-editor]').forEach(enhance);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
