@extends('layouts.panel')

@section('title', 'Prodotti · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    @php($canManage = auth()->user()->can(\App\Support\Permissions::CATALOG_MANAGE))

    {{-- Titolo, numero e filtri su una riga sola: l'elenco parte subito sotto. --}}
    <div class="ksm-listhead">
        <h1>Prodotti <span class="ksm-listhead__count">{{ number_format($products->total(), 0, ',', '.') }}</span></h1>

        @if ($company)
            <span class="ksm-listhead__chip">
                {{ $company->name }}
                <a href="{{ route('admin.products.index', array_filter(request()->only(['cerca', 'stato']))) }}" aria-label="Mostra tutte le aziende">
                    <x-icon name="close" :size="14" />
                </a>
            </span>
        @endif

        <form method="GET" class="ksm-listhead__filters" role="search">
            <span class="ksm-listhead__search">
                <x-icon name="search" :size="16" />
                <input class="ksm-input" type="search" name="cerca" value="{{ request('cerca') }}" placeholder="Cerca per nome" aria-label="Cerca per nome">
            </span>
            <select class="ksm-select" name="stato" aria-label="Stato" onchange="this.form.submit()">
                <option value="">Tutti gli stati</option>
                <option value="active" @selected(request('stato') === 'active')>Attivi</option>
                <option value="inactive" @selected(request('stato') === 'inactive')>Non attivi</option>
            </select>
            @if ($company)
                <input type="hidden" name="azienda" value="{{ $company->id }}">
            @endif
            <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Cerca</button>
            @if (request()->hasAny(['cerca', 'stato']))
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('admin.products.index', array_filter(['azienda' => $company?->id])) }}">Azzera</a>
            @endif
        </form>
    </div>

    @if ($canManage && $products->isNotEmpty())
        @include('partials.bulk-bar', [
            'action' => route('admin.products.bulk'),
            'paginator' => $products,
            'noun' => 'prodotti',
            'nounOne' => 'prodotto',
            'actions' => [
                'activate' => 'Attiva',
                'deactivate' => 'Disattiva',
                'kmoney' => 'Cambia quota KMoney',
                'delete' => 'Elimina',
            ],
            'percentSteps' => \App\Payments\KMoney\KMoneyShare::STEPS,
        ])
    @endif

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead>
            <tr>
                @if ($canManage)
                    <th class="ksm-bulk__cell"><input type="checkbox" data-bulk-page aria-label="Seleziona tutti in questa pagina"></th>
                @endif
                <th>Prodotto</th>
                <th>Azienda</th>
                <th>Prezzo</th>
                <th>KMoney</th>
                <th>Stato</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($products as $product)
                <tr>
                    @if ($canManage)
                        <td class="ksm-bulk__cell">
                            <input type="checkbox" name="ids[]" value="{{ $product->id }}" form="bulk" data-bulk-item
                                   aria-label="Seleziona {{ $product->name }}">
                        </td>
                    @endif
                    <td>
                        <a href="{{ route($canManage ? 'admin.products.edit' : 'admin.products.show', $product) }}"
                           style="display: flex; gap: 12px; align-items: center; color: var(--ksm-ink); font-weight: 600;">
                            <span class="ksm-thumb" aria-hidden="true"
                                  @if ($product->featured_image) style="background-image: url('{{ asset('storage/'.\App\Support\Images\ImageStore::thumb($product->featured_image)) }}'); background-size: cover;" @endif></span>
                            <span>
                                {{ $product->name }}
                                @if ($product->category)
                                    <small class="ksm-muted" style="display: block; font-weight: 400;">{{ $product->category->name }}</small>
                                @endif
                            </span>
                        </a>
                    </td>
                    <td>{{ $product->company?->name }}</td>
                    <td style="white-space: nowrap;">{{ \App\Support\Money::format($product->price) }}</td>
                    <td style="white-space: nowrap;">
                        {{ $product->kmoney_percent }}%
                        @if ($product->kmoney_discount_percent !== null)
                            <small class="ksm-muted">scelta</small>
                        @endif
                    </td>
                    <td>
                        <span class="ksm-badge @if ($product->status !== 'active') ksm-badge--muted @endif">
                            {{ $product->status === 'active' ? 'Attivo' : 'Non attivo' }}
                        </span>
                    </td>
                    <td>
                        @if ($canManage)
                            <div class="ksm-rowactions" style="flex-wrap: nowrap;">
                                <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route('admin.products.edit', $product) }}">Modifica</a>
                                <form method="POST" action="{{ route('admin.products.status', $product) }}">
                                    @csrf @method('PATCH')
                                    <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">{{ $product->status === 'active' ? 'Disattiva' : 'Attiva' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}"
                                      onsubmit="return confirm('Eliminare il prodotto?');">
                                    @csrf @method('DELETE')
                                    <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Elimina</button>
                                </form>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $canManage ? 7 : 6 }}" class="ksm-muted">Nessun prodotto.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $products->links() }}</div>
@endsection
