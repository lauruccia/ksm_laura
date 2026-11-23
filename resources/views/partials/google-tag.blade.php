{{-- Tag Google (GTM/GA). Si attiva solo se GOOGLE_TAG_ID è valorizzato nel .env. --}}
@if ($googleTagId = config('services.google_tag.id'))
<script async src="https://www.googletagmanager.com/gtm.js?id={{ $googleTagId }}"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
</script>
@endif
