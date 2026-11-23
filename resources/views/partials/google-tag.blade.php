{{-- Tag Google (GTM/GA) con Consent Mode v2: tutto negato finche' il visitatore non accetta (vedi js/consent.js).
     Si attiva solo se GOOGLE_TAG_ID e' valorizzato nel .env (ID di misurazione G-... oppure contenitore GTM-...). --}}
@if ($googleTagId = config('services.google_tag.id'))
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('consent', 'default', {
        ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied',
        analytics_storage: 'denied', wait_for_update: 500
    });
    try {
        if (localStorage.getItem('ksm_consent') === 'granted') {
            gtag('consent', 'update', {
                ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted',
                analytics_storage: 'granted'
            });
        }
    } catch (e) {}
    gtag('js', new Date());
    @if (str_starts_with($googleTagId, 'G-'))
    gtag('config', @json($googleTagId));
    @endif
</script>
<script async src="https://www.googletagmanager.com/{{ str_starts_with($googleTagId, 'G-') ? 'gtag/js' : 'gtm.js' }}?id={{ $googleTagId }}"></script>
@endif
