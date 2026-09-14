@props(['placement', 'city' => null, 'category' => null])

{{-- Un banner del circuito, se c'e' una campagna in corso per questa posizione e questo contesto. --}}
@php($campaign = app(\App\Support\Ads\AdServer::class)->pick($placement, \App\Support\Ads\AdContext::current($city, $category)))

@if ($campaign)
    <div {{ $attributes->merge(['class' => 'ksm-ad']) }} data-ad-view="{{ $campaign->viewUrl() }}">
        <a href="{{ route('ads.click', $campaign) }}" rel="sponsored noopener" target="_blank">
            <img src="{{ asset('storage/'.$campaign->img) }}" alt="{{ $campaign->name }}" loading="lazy">
        </a>
        <small class="ksm-ad__label">Pubblicità</small>
    </div>
@endif
