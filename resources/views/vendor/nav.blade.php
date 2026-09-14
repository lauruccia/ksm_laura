@php
    use App\Support\PlanCapabilities;

    // Le voci della vendita compaiono solo se il piano le comprende.
    $canSell = (bool) auth()->user()?->company?->allows(PlanCapabilities::SHOP);
@endphp

<li><a href="{{ route('vendor.dashboard') }}" @if (request()->routeIs('vendor.dashboard')) aria-current="page" @endif>Riepilogo</a></li>
@if ($canSell)
    <li><a href="{{ route('vendor.products.index') }}" @if (request()->routeIs('vendor.products.*')) aria-current="page" @endif>Prodotti</a></li>
    <li><a href="{{ route('vendor.orders.index') }}" @if (request()->routeIs('vendor.orders.*')) aria-current="page" @endif>Ordini</a></li>
@endif
<li><a href="{{ route('vendor.profile.edit') }}" @if (request()->routeIs('vendor.profile.*')) aria-current="page" @endif>Profilo azienda</a></li>
@if ($canSell)
    <li><a href="{{ route('vendor.payments.edit') }}" @if (request()->routeIs('vendor.payments.*')) aria-current="page" @endif>Incassi</a></li>
    <li><a href="{{ route('vendor.kmoney.edit') }}" @if (request()->routeIs('vendor.kmoney.*')) aria-current="page" @endif>KMoney</a></li>
@endif
@if (auth()->user()?->company?->advertiser)
    <li><a href="{{ route('vendor.advertising.index') }}" @if (request()->routeIs('vendor.advertising.*')) aria-current="page" @endif>Pubblicità</a></li>
@endif
<li><a href="{{ route('subscription.index') }}" @if (request()->routeIs('subscription.*')) aria-current="page" @endif>Piano</a></li>
