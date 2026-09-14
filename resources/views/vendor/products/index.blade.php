@extends('layouts.panel')

@section('title', 'Prodotti · KSM')
@section('role', 'Area azienda')
@section('nav')@include('vendor.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>Prodotti</h1>
        <a class="ksm-btn ksm-btn--primary" href="{{ route('vendor.products.create') }}">Nuovo prodotto</a>
    </div>

    {{-- Quota KMoney su piu' prodotti insieme: le caselle delle righe appartengono a questo modulo. --}}
    @if ($inDebt)
        <p class="ksm-alert ksm-alert--error">
            Il conto KMoney è in debito: tutti i prodotti si pagano al 100% in KMoney finché non torna in positivo.
        </p>
    @else
        <form id="kmoney-bulk" method="POST" action="{{ route('vendor.products.kmoney') }}" class="ksm-filters">
            @csrf @method('PATCH')
            <label class="ksm-label" for="bulk_percent" style="margin: 0;">Quota KMoney dei prodotti selezionati</label>
            <select class="ksm-select" id="bulk_percent" name="percent">
                <option value="auto">Automatica</option>
                @foreach (\App\Payments\KMoney\KMoneyShare::STEPS as $step)
                    <option value="{{ $step }}">{{ $step }}%</option>
                @endforeach
            </select>
            <button class="ksm-btn ksm-btn--ghost" type="submit">Applica</button>
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('vendor.kmoney.edit') }}">Quote per categoria</a>
        </form>
        @error('products')<p class="ksm-error">{{ $message }}</p>@enderror
    @endif

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead>
            <tr>
                <th aria-label="Seleziona"></th>
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
                    <td>
                        @unless ($inDebt)
                            <input type="checkbox" name="products[]" value="{{ $product->id }}" form="kmoney-bulk"
                                   aria-label="Seleziona {{ $product->name }}">
                        @endunless
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
                    <td>{{ $product->stock }}</td>
                    <td>
                        <span class="ksm-badge @if ($product->status !== 'active') ksm-badge--muted @endif">
                            {{ $product->status }}
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
