{{-- Popup del circuito: lo apre ads.js dopo qualche secondo, al massimo una volta al giorno per visitatore. --}}
@php($campaign = app(\App\Support\Ads\AdServer::class)->pick(\App\Support\Ads\Placements::HOME_POPUP, \App\Support\Ads\AdContext::current()))

@if ($campaign)
    <dialog class="ksm-ad-popup" data-ad-popup="{{ $campaign->id }}" data-ad-view="{{ $campaign->viewUrl() }}"
            aria-label="Pubblicità">
        <form method="dialog" class="ksm-ad-popup__close">
            <button type="submit" aria-label="Chiudi">&times;</button>
        </form>
        <a href="{{ route('ads.click', $campaign) }}" rel="sponsored noopener" target="_blank">
            <img src="{{ asset('storage/'.$campaign->img) }}" alt="{{ $campaign->name }}" loading="lazy" decoding="async">
        </a>
        <small class="ksm-ad__label">Pubblicità</small>
    </dialog>
@endif
