(function () {
    var KEY = 'ksm_consent';
    var box = document.getElementById('ksm-consent');
    if (!box) { return; }

    function read() {
        try { return localStorage.getItem(KEY); } catch (e) { return null; }
    }

    function save(value) {
        try { localStorage.setItem(KEY, value); } catch (e) {}
    }

    function apply(value) {
        var state = value === 'granted' ? 'granted' : 'denied';
        if (typeof window.gtag === 'function') {
            window.gtag('consent', 'update', {
                ad_storage: state, ad_user_data: state, ad_personalization: state,
                analytics_storage: state
            });
        }
    }

    function show() { box.hidden = false; }
    function hide() { box.hidden = true; }

    box.addEventListener('click', function (event) {
        var button = event.target.closest('[data-ksm-consent]');
        if (!button) { return; }
        var value = button.getAttribute('data-ksm-consent');
        save(value);
        apply(value);
        hide();
    });

    // Link "Gestisci cookie" (nel piede della pagina): riapre la scelta.
    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-ksm-consent-open]')) {
            event.preventDefault();
            show();
        }
    });

    if (read() === null) { show(); }
})();
