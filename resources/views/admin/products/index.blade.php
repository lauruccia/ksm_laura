@extends('layouts.panel')

@section('title', 'Prodotti · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    @php($canManage = auth()->user()->can(\App\Support\Permissions::CATALOG_MANAGE))

    <div class="ksm-panel__head"><h1>Prodotti</h1></div>

    <form method="GET" style="display: flex; gap: 8px; margin-bottom: 18px; max-width: 420px;">
        <input class="ksm-input" name="cerca" value="{{ request('cerca') }}" placeholder="Cerca">
        @if ($company)
            <input type="hidden" name="azienda" value="{{ $company->id }}">
        @endif
        <button class="ksm-btn ksm-btn--ghost" type="submit">Cerca</button>
    </form>

    @if ($company)
        <p class="ksm-muted">
            Prodotti di <strong>{{ $company->name }}</strong> ·
            <a href="{{ route('admin.products.index', array_filter(['cerca' => request('cerca')])) }}">tutte le aziende</a>
        </p>
    @endif

    {{-- Quota KMoney su piu' prodotti insieme: le caselle delle righe appartengono a questo modulo. --}}
    @if ($canManage)
        <form id="kmoney-bulk" method="POST" action="{{ route('admin.products.kmoney') }}" class="ksm-filters">
            @csrf @method('PATCH')
            <label class="ksm-label" for="bulk_percent" style="margin: 0;">Quota KMoney dei prodotti selezionati</label>
            <select class="ksm-select" id="bulk_percent" name="percent">
                <option value="auto">Automatica</option>
                @foreach (\App\Payments\KMoney\KMoneyShare::STEPS as $step)
                    <option value="{{ $step }}">{{ $step }}%</option>
                @endforeach
            </select>
            <button class="ksm-btn ksm-btn--ghost" type="submit">Applica</button>
        </form>
        @error('products')<p class="ksm-error">{{ $message }}</p>@enderror
    @endif

    <div class="ksm-table-wrap">
        <table class="ksm-table">
            <thead>
            <tr>
                @if ($canManage)<th aria-label="Seleziona"></th>@endif
                <th>Nome</th>
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
                        <td>
                            <input type="checkbox" name="products[]" value="{{ $product->id }}" form="kmoney-bulk"
                                   aria-label="Seleziona {{ $product->name }}">
                        </td>
                    @endif
                    <td><a href="{{ route($canManage ? 'admin.products.edit' : 'admin.products.show', $product) }}">{{ $product->name }}</a></td>
                    <td>{{ $product->company?->name }}</td>
                    <td>{{ \App\Support\Money::format($product->price) }}</td>
                    <td style="white-space: nowrap;">
                        {{ $product->kmoney_percent }}%
                        @if ($product->kmoney_discount_percent !== null)
                            <small class="ksm-muted">scelta</small>
                        @endif
                    </td>
                    <td>
                        <span class="ksm-badge @if ($product->status !== 'active') ksm-badge--muted @endif">
                            {{ $product->status }}
                        </span>
                    </td>
                    <td style="text-align: right; white-space: nowrap;">
                        @if ($canManage)
                            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.products.edit', $product) }}">Modifica</a>
                            <form method="POST" action="{{ route('admin.products.status', $product) }}" style="display: inline;">
                                @csrf @method('PATCH')
                                <button class="ksm-btn ksm-btn--ghost" type="submit">Cambia stato</button>
                            </form>
                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}"
                                  style="display: inline;" onsubmit="return confirm('Eliminare il prodotto?');">
                                @csrf @method('DELETE')
                                <button class="ksm-btn ksm-btn--ghost" type="submit">Elimina</button>
                            </form>
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
