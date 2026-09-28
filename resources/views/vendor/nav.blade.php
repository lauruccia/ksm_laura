@php
    use App\Support\PlanCapabilities;

    $company = auth()->user()?->company;

    // Le voci della vendita compaiono solo se il piano le comprende.
    $canSell = (bool) $company?->allows(PlanCapabilities::SHOP);
@endphp

<li><a href="{{ route('vendor.dashboard') }}" @if (request()->routeIs('vendor.dashboard')) aria-current="page" @endif><x-icon name="dashboard" :size="18" /><span>Riepilogo</span></a></li>

@if ($canSell)
    <li class="ksm-panel__sep">Negozio</li>
    <li><a href="{{ route('vendor.products.index') }}" @if (request()->routeIs('vendor.products.*') && ! request()->routeIs('vendor.products.create')) aria-current="page" @endif><x-icon name="box" :size="18" /><span>Prodotti</span></a></li>
    <li><a href="{{ route('vendor.products.create') }}" @if (request()->routeIs('vendor.products.create')) aria-current="page" @endif><x-icon name="plus" :size="18" /><span>Nuovo prodotto</span></a></li>
    <li><a href="{{ route('vendor.orders.index') }}" @if (request()->routeIs('vendor.orders.*')) aria-current="page" @endif><x-icon name="cart" :size="18" /><span>Ordini ricevuti</span></a></li>
    <li><a href="{{ route('vendor.kmoney.edit') }}" @if (request()->routeIs('vendor.kmoney.*')) aria-current="page" @endif><x-icon name="sliders" :size="18" /><span>Quote KMoney</span></a></li>
@endif

<li class="ksm-panel__sep">Azienda</li>
<li><a href="{{ route('vendor.profile.edit') }}" @if (request()->routeIs('vendor.profile.*')) aria-current="page" @endif><x-icon name="building" :size="18" /><span>Profilo azienda</span></a></li>
@if ($canSell)
    <li><a href="{{ route('vendor.payments.edit') }}" @if (request()->routeIs('vendor.payments.*')) aria-current="page" @endif><x-icon name="card" :size="18" /><span>Metodi di incasso</span></a></li>
@endif
@if ($company?->advertiser)
    <li><a href="{{ route('vendor.advertising.index') }}" @if (request()->routeIs('vendor.advertising.*')) aria-current="page" @endif><x-icon name="megaphone" :size="18" /><span>Pubblicità</span></a></li>
@endif
<li><a href="{{ route('subscription.index') }}" @if (request()->routeIs('subscription.*')) aria-current="page" @endif><x-icon name="award" :size="18" /><span>Piano</span></a></li>

<li class="ksm-panel__sep">Sul sito</li>
@if (filled($company?->slug))
    <li><a href="{{ route('companies.show', ['company' => $company->slug]) }}"><x-icon name="external" :size="18" /><span>La mia scheda pubblica</span></a></li>
@endif
<li><a href="{{ route('products.index') }}"><x-icon name="grid" :size="18" /><span>Catalogo</span></a></li>
<li><a href="{{ route('cart.index') }}"><x-icon name="cart" :size="18" /><span>Carrello</span></a></li>

{{-- L'azienda compra anche: i suoi acquisti stanno nell'area personale. --}}
<li class="ksm-panel__sep">Altre aree</li>
<li><a href="{{ route('account.dashboard') }}"><x-icon name="user" :size="18" /><span>Il mio account</span></a></li>
