@extends('layouts.panel')

@section('title', 'Prodotti · KSM')
@section('role', 'Area azienda')
@section('nav')@include('vendor.nav')@endsection

@section('content')
    {{-- Titolo, numero e filtri su una riga sola: l'elenco parte subito sotto. --}}
    <div class="ksm-listhead">
        <h1>Prodotti <span class="ksm-listhead__count">{{ number_format($products->total(), 0, ',', '.') }}</span></h1>

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
            <button class="ksm-btn ksm-btn--ghost ksm-btn--sm" type="submit">Cerca</button>
            @if (request()->hasAny(['cerca', 'stato']))
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('vendor.products.index') }}">Azzera</a>
            @endif
        </form>

        <span class="ksm-listhead__actions">
            @unless ($inDebt)
                <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route('vendor.kmoney.edit') }}">Quote KMoney</a>
            @endunless
            <a class="ksm-btn ksm-btn--primary ksm-btn--sm" href="{{ route('vendor.products.create') }}">Nuovo prodotto</a>
        </span>
    </div>

    @if ($inDebt)
        <p class="ksm-alert ksm-alert--error">
            Il conto KMoney è in debito: tutti i prodotti si pagano al 100% in KMoney finché non torna in positivo.
        </p>
    @endif

    {{-- Azioni su piu' prodotti insieme: le caselle delle righe appartengono al modulo della barra. --}}
    @if ($products->isNotEmpty())
        @include('partials.bulk-bar', [
            'action' => route('vendor.products.bulk'),
            'paginator' => $products,
            'noun' => 'prodotti',
            'nounOne' => 'prodotto',
            'actions' => array_filter([
                'activate' => 'Attiva',
                'deactivate' => 'Disattiva',
                'kmoney' => $inDebt ? null : 'Cambia quota KMoney',
                'delete' => 'Elimina',
            ]),
            'percentSteps' => $kmoneySteps,
        ])
    @endif

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead>
            <tr>
                <th class="ksm-bulk__cell"><input type="checkbox" data-bulk-page aria-label="Seleziona tutti in questa pagina"></th>
                <th>Nome</th>
                <th>Categoria</th>
                <th>Prezzo</th>
                <th>KMoney</th>
                <th>Disponibilità</th>
                <th>Stato</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($products as $product)
                <tr>
                    <td class="ksm-bulk__cell">
                        <input type="checkbox" name="ids[]" value="{{ $product->id }}" form="bulk" data-bulk-item
                               aria-label="Seleziona {{ $product->name }}">
                    </td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->category?->name }}</td>
                    <td>{{ \App\Support\Money::format($product->price) }}</td>
                    <td style="white-space: nowrap;">
                        {{ $product->kmoney_percent }}%
                        @if ($product->kmoney_discount_percent !== null)
                            <small class="ksm-muted">scelta</small>
                        @endif
                    </td>
                    <td>{{ $product->product_type === 'variable' ? 'Per variante' : ($product->stock ?? 'Non gestito') }}</td>
                    <td>
                        <span class="ksm-badge @if ($product->status !== 'active') ksm-badge--muted @endif">
                            {{ $product->status === 'active' ? 'Attivo' : 'Non attivo' }}
                        </span>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                        <a class="ksm-btn ksm-btn--ghost" href="{{ route('vendor.products.edit', $product) }}">Modifica</a>
                        <form method="POST" action="{{ route('vendor.products.destroy', $product) }}"
                              style="display: inline;" onsubmit="return confirm('Eliminare il prodotto?');">
                            @csrf @method('DELETE')
                            <button class="ksm-btn ksm-btn--ghost" type="submit">Elimina</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="ksm-muted">Nessun prodotto.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 20px;">{{ $products->links() }}</div>
@endsection
