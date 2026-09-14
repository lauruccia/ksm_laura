<li><a href="{{ route('account.dashboard') }}" @if (request()->routeIs('account.dashboard')) aria-current="page" @endif>Riepilogo</a></li>
<li><a href="{{ route('account.orders.index') }}" @if (request()->routeIs('account.orders.*')) aria-current="page" @endif>I miei ordini</a></li>
<li><a href="{{ route('account.profile.edit') }}" @if (request()->routeIs('account.profile.*')) aria-current="page" @endif>Dati e indirizzo</a></li>

<li class="ksm-panel__sep">Sul sito</li>
<li><a href="{{ route('products.index') }}">Catalogo</a></li>
<li><a href="{{ route('companies.index') }}">Aziende</a></li>
<li><a href="{{ route('cart.index') }}">Carrello</a></li>

@auth
    @if (auth()->user()->isVendor())
        <li class="ksm-panel__sep">Altre aree</li>
        <li><a href="{{ route('vendor.dashboard') }}">Area azienda</a></li>
    @elseif (auth()->user()->isAdmin())
        <li class="ksm-panel__sep">Altre aree</li>
        <li><a href="{{ route('admin.dashboard') }}">Amministrazione</a></li>
    @endif
@endauth
