@extends('layouts.app')

@section('title', __('site.cart'))

@section('content')
    <section class="ksm-section">
        <div class="ksm-container">
            <div class="ksm-section-head"><h2>{{ __('site.cart') }}</h2></div>

            @if ($items->isEmpty())
                <p class="ksm-muted">Il carrello e vuoto.</p>
                <a class="ksm-btn ksm-btn--primary" href="{{ route('products.index') }}">{{ __('site.nav_products') }}</a>
            @else
                <div class="ksm-table-wrap">
                    <table class="ksm-table">
                        <thead>
                        <tr>
                            <th>Prodotto</th>
                            <th>Prezzo</th>
                            <th>Quantità</th>
                            <th>Totale</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td><a href="{{ route('products.show', $item['slug']) }}">{{ $item['name'] }}</a></td>
                                <td>{{ \App\Support\Money::format($item['price']) }}</td>
                                <td>
                                    <form method="POST" action="{{ route('cart.update', $item['slug']) }}" style="display: flex; gap: 6px;">
                                        @csrf @method('PATCH')
                                        <input class="ksm-input" style="width: 80px;" type="number" name="quantita"
                                               value="{{ $item['quantity'] }}" min="0">
                                        <button class="ksm-btn ksm-btn--ghost" type="submit">Aggiorna</button>
                                    </form>
                                </td>
                                <td>{{ \App\Support\Money::format($item['price'] * $item['quantity']) }}</td>
                                <td>
                                    <form method="POST" action="{{ route('cart.remove', $item['slug']) }}">
                                        @csrf @method('DELETE')
                                        <button class="ksm-btn ksm-btn--ghost" type="submit">Rimuovi</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 22px; gap: 16px; flex-wrap: wrap;">
                    <form method="POST" action="{{ route('cart.clear') }}">
                        @csrf @method('DELETE')
                        <button class="ksm-btn ksm-btn--ghost" type="submit">Svuota carrello</button>
                    </form>

                    <div style="text-align: right;">
                        <p class="ksm-product__price" style="font-size: 1.3rem;">
                            {{ \App\Support\Money::format($subtotal) }}
                        </p>
                        <a class="ksm-btn ksm-btn--primary" href="{{ route('checkout.show') }}">Vai al pagamento</a>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
