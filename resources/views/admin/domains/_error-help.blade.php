{{--
    Il "?" accanto a un errore rosso: passandoci sopra (o toccandolo) spiega
    cosa fare, passo per passo. I consigli vengono da DomainErrorHelp.
--}}
@php($help = \App\Support\Domains\DomainErrorHelp::for($error ?? null))
@if ($help)
    <span class="ksm-errhelp" data-errhelp>
        <button class="ksm-errhelp__btn" type="button" aria-expanded="false" aria-label="Come risolvere: {{ $help['title'] }}" data-errhelp-btn>?</button>
        <span class="ksm-errhelp__pop" role="tooltip" hidden data-errhelp-pop>
            <strong class="ksm-errhelp__title">{{ $help['title'] }}</strong>
            <ol class="ksm-errhelp__steps">
                @foreach ($help['steps'] as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
            @if ($help['tip'])
                <span class="ksm-errhelp__tip">💡 {{ $help['tip'] }}</span>
            @endif
        </span>
    </span>

    @once
        <style>
            .ksm-errhelp { display: inline-flex; flex: none; vertical-align: middle; }
            .ksm-errhelp__btn {
                display: inline-grid; place-items: center; width: 18px; height: 18px; padding: 0;
                border: 0; border-radius: 50%; background: var(--ksm-danger); color: #fff;
                font: 700 .72rem/1 inherit; cursor: pointer;
            }
            .ksm-errhelp__btn:hover, .ksm-errhelp__btn[aria-expanded="true"] { filter: brightness(1.15); }
            .ksm-errhelp__btn:focus-visible { outline: 2px solid var(--ksm-accent, #2563eb); outline-offset: 2px; }
            /* Fisso sullo schermo: la tabella scorre di lato e taglierebbe un riquadro assoluto. */
            .ksm-errhelp__pop {
                position: fixed; z-index: 1000; display: block; width: 380px; box-sizing: border-box;
                max-height: min(70vh, 460px); overflow: auto; padding: 14px 16px;
                border: 1px solid var(--ksm-line); border-radius: var(--ksm-radius-lg, 12px);
                background: #fff; color: var(--ksm-body); box-shadow: 0 12px 32px rgba(15, 23, 42, .18);
                font-size: .84rem; line-height: 1.45; white-space: normal; text-align: left; cursor: auto;
            }
            .ksm-errhelp__pop[hidden] { display: none; }
            .ksm-errhelp__title { display: block; margin-bottom: 6px; color: var(--ksm-ink); font-size: .9rem; }
            .ksm-errhelp__steps { margin: 0; padding-left: 20px; display: grid; gap: 5px; }
            .ksm-errhelp__tip { display: block; margin-top: 10px; padding: 8px 10px; border-radius: 8px; background: var(--ksm-line-soft); color: var(--ksm-body); font-size: .8rem; }
        </style>
        <script>
            (() => {
                let open = null, timer = null;

                const place = (btn, pop) => {
                    const vw = document.documentElement.clientWidth, vh = document.documentElement.clientHeight, m = 12;
                    pop.style.width = Math.min(380, vw - 2 * m) + 'px';
                    const r = btn.getBoundingClientRect();
                    const w = pop.offsetWidth, h = pop.offsetHeight;
                    const left = Math.max(m, Math.min(r.left - 12, vw - w - m));
                    let top = r.bottom + 8;
                    if (top + h > vh - m) top = Math.max(m, r.top - h - 8);
                    pop.style.left = left + 'px';
                    pop.style.top = top + 'px';
                };
                const show = (box) => {
                    clearTimeout(timer);
                    if (open && open !== box) hide(open);
                    const btn = box.querySelector('[data-errhelp-btn]'), pop = box.querySelector('[data-errhelp-pop]');
                    pop.hidden = false;
                    btn.setAttribute('aria-expanded', 'true');
                    place(btn, pop);
                    open = box;
                };
                const hide = (box) => {
                    if (!box) return;
                    box.querySelector('[data-errhelp-pop]').hidden = true;
                    box.querySelector('[data-errhelp-btn]').setAttribute('aria-expanded', 'false');
                    if (open === box) open = null;
                };
                const later = (box) => { clearTimeout(timer); timer = setTimeout(() => hide(box), 250); };

                document.addEventListener('mouseover', (e) => {
                    const box = e.target.closest('[data-errhelp]');
                    if (box) show(box);
                });
                document.addEventListener('mouseout', (e) => {
                    const box = e.target.closest('[data-errhelp]');
                    if (box && !box.contains(e.relatedTarget)) later(box);
                });
                document.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-errhelp-btn]');
                    if (btn) {
                        // Sul telefono non c'e' il passaggio del mouse: il tocco apre e chiude.
                        e.preventDefault();
                        e.stopPropagation();
                        const box = btn.closest('[data-errhelp]');
                        open === box && e.pointerType !== 'mouse' ? hide(box) : show(box);
                    } else if (open && !open.contains(e.target)) {
                        hide(open);
                    }
                });
                document.addEventListener('focusin', (e) => {
                    const box = e.target.closest('[data-errhelp]');
                    if (box) show(box); else if (open) hide(open);
                });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && open) { const b = open; hide(b); b.querySelector('button').focus(); } });
                // Se la pagina o la tabella scorrono, il riquadro segue il "?" (lo scorrimento dentro il riquadro non conta).
                const follow = (e) => {
                    if (!open || (e.target instanceof Node && open.querySelector('[data-errhelp-pop]').contains(e.target))) return;
                    place(open.querySelector('[data-errhelp-btn]'), open.querySelector('[data-errhelp-pop]'));
                };
                window.addEventListener('scroll', follow, true);
                window.addEventListener('resize', follow);
            })();
        </script>
    @endonce
@endif
