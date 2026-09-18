@extends('layouts.app')

@section('title', __('site.cart'))

@section('content')
    <section class="ksm-section">
        <div class="ksm-container">
            <div class="ksm-section-head"><h2>{{ __('site.cart') }}</h2></div>

            {{-- Un ordine e' di un venditore solo. Con piu' venditori i carrelli stanno
                 in schede sopra la tabella: si vede quale si paga e si cambia con un click. --}}
            @if ($carts->count() > 1 || ($carts->isNotEmpty() && $items->isEmpty()))
                <div class="ksm-cart-vendors">
                    <p class="ksm-cart-vendors__intro">
                        @if ($items->isEmpty())
                            <strong>Hai ancora prodotti salvati.</strong> Scegli il venditore da cui vuoi ordinare.
                        @else
                            <strong>Hai prodotti di {{ $carts->count() }} venditori.</strong>
                            Ogni venditore è un ordine a parte: paghi un carrello alla volta, gli altri restano salvati.
                        @endif
                    </p>

                    <ul class="ksm-cart-vendors__list">
                        @foreach ($carts as $cart)
                            <li @class(['ksm-cart-vendor', 'is-active' => $cart['active']])>
                                @if ($cart['active'])
                                    <div class="ksm-cart-vendor__main" aria-current="true">
                                        <span class="ksm-cart-vendor__state">Stai ordinando da</span>
                                        <strong class="ksm-cart-vendor__name">{{ $cart['company']->name }}</strong>
                                        <span class="ksm-cart-vendor__meta">
                                            {{ trans_choice('{1} :count prodotto|[2,*] :count prodotti', $cart['count'], ['count' => $cart['count']]) }}
                                            · {{ \App\Support\Money::format($cart['subtotal']) }}
                                        </span>
                                    </div>
                                @else
                                    <form method="POST" action="{{ route('cart.open', $cart['company']) }}">
                                        @csrf
                                        <button class="ksm-cart-vendor__main" type="submit">
                                            <span class="ksm-cart-vendor__state">Salvato · passa a questo carrello &rarr;</span>
                                            <strong class="ksm-cart-vendor__name">{{ $cart['company']->name }}</strong>
                                            <span class="ksm-cart-vendor__meta">
                                                {{ trans_choice('{1} :count prodotto|[2,*] :count prodotti', $cart['count'], ['count' => $cart['count']]) }}
                                                · {{ \App\Support\Money::format($cart['subtotal']) }}
                                            </span>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('cart.discard', $cart['company']) }}">
                                        @csrf @method('DELETE')
                                        <button class="ksm-cart-vendor__remove" type="submit"
                                                aria-label="Elimina il carrello di {{ $cart['company']->name }}"
                                                title="Elimina questo carrello">&times;</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @elseif ($company)
                <p class="ksm-cart-vendors__single">
                    Stai ordinando da
                    <a href="{{ route('companies.show', $company->slug) }}"><strong>{{ $company->name }}</strong></a>
                </p>
            @endif

            @if ($carts->isEmpty())
                <p class="ksm-muted">Il carrello e vuoto.</p>
                <a class="ksm-btn ksm-btn--primary" href="{{ route('products.index') }}">{{ __('site.nav_products') }}</a>
            @elseif ($items->isNotEmpty())
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
                                        <input type="hidden" name="variant_id" value="{{ $item['variant_id'] ?? '' }}">
                                        <input class="ksm-input" style="width: 80px;" type="number" name="quantita"
                                               value="{{ $item['quantity'] }}" min="0">
                                        <button class="ksm-btn ksm-btn--ghost" type="submit">Aggiorna</button>
                                    </form>
                                </td>
                                <td>{{ \App\Support\Money::format($item['price'] * $item['quantity']) }}</td>
                                <td>
                                    <form method="POST" action="{{ route('cart.remove', $item['slug']) }}">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="variant_id" value="{{ $item['variant_id'] ?? '' }}">
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
                        @auth
                            <a class="ksm-btn ksm-btn--primary" href="{{ route('checkout.show') }}">Vai al pagamento</a>
                        @else
                            <a class="ksm-btn ksm-btn--primary" href="#acquisto-accesso">Vai al pagamento</a>
                        @endauth
                    </div>
                </div>

                @guest
                    {{-- Chi compra da ospite si registra o accede qui sotto,
                         senza lasciare il carrello. --}}
                    <x-auth-inline class="ksm-cart-auth" ritorno="pagamento"
                                   title="Accedi o registrati per andare al pagamento" />
                @endguest
            @endif
        </div>
    </section>
@endsection
