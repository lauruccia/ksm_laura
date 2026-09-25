@php use App\Support\Permissions as P; @endphp

{{-- Ogni voce compare solo a chi ha il permesso della pagina a cui porta:
     un menu che porta a un 403 e' peggio di un menu piu' corto. --}}

@can(P::DASHBOARD_VIEW)
    <li><a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif><x-icon name="dashboard" :size="18" /><span>Riepilogo</span></a></li>
@endcan

@canany([P::COMPANIES_VIEW, P::CATALOG_VIEW])
    <li class="ksm-panel__sep">Marketplace</li>
@endcanany

@can(P::COMPANIES_VIEW)
    <li><a href="{{ route('admin.companies.index') }}" @if (request()->routeIs('admin.companies.*')) aria-current="page" @endif><x-icon name="building" :size="18" /><span>Aziende</span></a></li>
@endcan
@can(P::COMPANIES_MANAGE)
    <li><a href="{{ route('admin.company_categories.index') }}" @if (request()->routeIs('admin.company_categories.*')) aria-current="page" @endif><x-icon name="grid" :size="18" /><span>Categorie aziende</span></a></li>
@endcan
@can(P::CATALOG_VIEW)
    <li><a href="{{ route('admin.products.index') }}" @if (request()->routeIs('admin.products.*')) aria-current="page" @endif><x-icon name="box" :size="18" /><span>Prodotti</span></a></li>
    <li><a href="{{ route('admin.product_categories.index') }}" @if (request()->routeIs('admin.product_categories.*')) aria-current="page" @endif><x-icon name="list" :size="18" /><span>Categorie prodotti</span></a></li>
    <li><a href="{{ route('admin.brands.index') }}" @if (request()->routeIs('admin.brands.*')) aria-current="page" @endif><x-icon name="tag" :size="18" /><span>Marche</span></a></li>
@endcan

@canany([P::ORDERS_VIEW, P::PAYMENTS_VIEW, P::PLANS_MANAGE, P::SUBSCRIPTIONS_VIEW])
    <li class="ksm-panel__sep">Vendite</li>
@endcanany

@can(P::ORDERS_VIEW)
    <li><a href="{{ route('admin.orders.index') }}" @if (request()->routeIs('admin.orders.*')) aria-current="page" @endif><x-icon name="cart" :size="18" /><span>Ordini</span></a></li>
@endcan
@can(P::PAYMENTS_VIEW)
    <li><a href="{{ route('admin.payments.index') }}" @if (request()->routeIs('admin.payments.*')) aria-current="page" @endif><x-icon name="card" :size="18" /><span>Pagamenti</span></a></li>
@endcan
@can(P::PLANS_MANAGE)
    <li><a href="{{ route('admin.plans.index') }}" @if (request()->routeIs('admin.plans.*')) aria-current="page" @endif><x-icon name="award" :size="18" /><span>Piani</span></a></li>
@endcan
@can(P::SUBSCRIPTIONS_VIEW)
    <li><a href="{{ route('admin.subscriptions.index') }}" @if (request()->routeIs('admin.subscriptions.*')) aria-current="page" @endif><x-icon name="refresh" :size="18" /><span>Abbonamenti</span></a></li>
@endcan

@can(P::CONTENT_MANAGE)
    <li class="ksm-panel__sep">Contenuti</li>
    <li><a href="{{ route('admin.cms.index') }}" @if (request()->routeIs('admin.cms.*')) aria-current="page" @endif><x-icon name="file" :size="18" /><span>Pagine</span></a></li>
    <li><a href="{{ route('admin.menus.edit') }}" @if (request()->routeIs('admin.menus.*')) aria-current="page" @endif><x-icon name="menu" :size="18" /><span>Menu</span></a></li>
    <li><a href="{{ route('admin.advertisements.index') }}" @if (request()->routeIs('admin.advertisements.*')) aria-current="page" @endif><x-icon name="megaphone" :size="18" /><span>Campagne banner</span></a></li>
    <li><a href="{{ route('admin.advertisers.index') }}" @if (request()->routeIs('admin.advertisers.*')) aria-current="page" @endif><x-icon name="briefcase" :size="18" /><span>Inserzionisti</span></a></li>
    <li><a href="{{ route('admin.domains.index') }}" @if (request()->routeIs('admin.domains.*')) aria-current="page" @endif><x-icon name="globe" :size="18" /><span>Domini</span></a></li>
@endcan

<li class="ksm-panel__sep">Sistema</li>

@can(P::USERS_VIEW)
    <li><a href="{{ route('admin.users.index') }}" @if (request()->routeIs('admin.users.*')) aria-current="page" @endif><x-icon name="users" :size="18" /><span>Utenti</span></a></li>
@endcan
@can(P::ROLES_MANAGE)
    <li><a href="{{ route('admin.roles.index') }}" @if (request()->routeIs('admin.roles.*')) aria-current="page" @endif><x-icon name="shield" :size="18" /><span>Ruoli e permessi</span></a></li>
@endcan
@can(P::SETTINGS_MANAGE)
    <li><a href="{{ route('admin.settings.edit') }}" @if (request()->routeIs('admin.settings.*')) aria-current="page" @endif><x-icon name="settings" :size="18" /><span>Impostazioni</span></a></li>
@endcan
