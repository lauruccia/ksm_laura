@extends('layouts.app')

@section('title', 'Pagamento')

@section('content')
    @php
        $money = fn ($value) => \App\Support\Money::format($value);
        $ky = fn ($value) => number_format((float) $value, 2, ',', '.').' KY';
        $blocked = ($split->hasEuro() && empty($methods))
            || ($split->hasKmoney() && ! $kmoneyAvailable && ! $euroFallback);
    @endphp

    <section class="ksm-section">
        <div class="ksm-container ksm-grid ksm-grid--2">
            <form class="ksm-card" style="padding: 22px;" method="POST" action="{{ route('checkout.process') }}">
                @csrf
                <h2>Dati di fatturazione</h2>

                {{-- Precompilati con l'indirizzo salvato nell'account, che
                     resta modificabile: l'ordine conserva cio' che si scrive qui. --}}
                @php($defaults = auth()->user()->billingDefaults())

                @foreach ([
                    'billing_name' => 'Nome e cognome',
                    'billing_email' => 'Email',
                    'billing_phone' => 'Telefono',
                    'billing_address' => 'Indirizzo',
                    'billing_city' => 'Città',
                    'billing_zip' => 'CAP',
                    'billing_country' => 'Paese',
                ] as $field => $label)
                    <div class="ksm-field">
                        <label class="ksm-label" for="{{ $field }}">{{ $label }}</label>
                        <input class="ksm-input" id="{{ $field }}" name="{{ $field }}"
                               value="{{ old($field, $defaults[$field] ?? '') }}">
                        @error($field)<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                @endforeach

                {{-- La quota KMoney si paga per prima, sul sito KMoney. --}}
                @if ($split->hasKmoney())
                    <fieldset class="ksm-field" style="border: 1px solid var(--ksm-line); border-radius: var(--ksm-radius); padding: 14px;">
                        <legend class="ksm-label" style="padding: 0 6px;">KMoney</legend>

                        <label style="display: flex; gap: 8px; align-items: flex-start; margin-bottom: 8px;">
                            <input type="radio" name="pagamento_kmoney" value="conto"
                                   @checked(old('pagamento_kmoney', 'conto') === 'conto')>
                            <span>
                                Ho un conto KMoney: pago {{ $ky($split->kmoney()) }} in KMoney
                                @if ($split->hasEuro()) e subito dopo {{ $money($split->euro()) }} in euro @endif.
                            </span>
                        </label>

                        @if ($euroFallback)
                            <label style="display: flex; gap: 8px; align-items: flex-start;">
                                <input type="radio" name="pagamento_kmoney" value="euro"
                                       @checked(old('pagamento_kmoney') === 'euro')>
                                <span>Non ho un conto KMoney: pago tutto in euro.</span>
                            </label>
                        @elseif ($split->vendorInDebt)
                            <small class="ksm-muted">Questo venditore accetta questi prodotti solo in KMoney.</small>
                        @endif

                        @unless ($kmoneyAvailable)
                            <p class="ksm-alert ksm-alert--error" style="margin: 10px 0 0;">
                                Il venditore non ha ancora collegato il suo conto KMoney.
                            </p>
                        @endunless

                        @error('pagamento_kmoney')<span class="ksm-error">{{ $message }}</span>@enderror
                    </fieldset>
                @endif

                <div class="ksm-field">
                    <label class="ksm-label" for="method">{{ $split->hasKmoney() ? 'Pagamento della parte in euro' : 'Metodo di pagamento' }}</label>

                    @if (empty($methods))
                        <p class="ksm-alert ksm-alert--error">
                            Questa azienda non ha ancora attivato un metodo di pagamento in euro.
                        </p>
                    @else
                        <select class="ksm-select" id="method" name="method">
                            @foreach ($methods as $method)
                                <option value="{{ $method }}" @selected(old('method') === $method)>
                                    {{ \App\Payments\GatewayManager::label($method) }}
                                </option>
                            @endforeach
                        </select>
                        <small class="ksm-muted">
                            @if ($split->hasKmoney() && ! $split->hasEuro())
                                Tutto l'ordine si paga in KMoney: il metodo in euro serve solo se scegli di pagare tutto in euro.
                            @else
                                Il pagamento si completa sul sito del gestore, poi torni qui.
                            @endif
                        </small>
                    @endif

                    @error('method')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit" @disabled($blocked)>
                    Vai al pagamento
                </button>
            </form>

            <aside class="ksm-card" style="padding: 22px; height: fit-content;">
                <h2>Riepilogo</h2>
                <p class="ksm-muted">Venditore: {{ $company->name }}</p>

                <ul style="list-style: none; padding: 0; margin: 0 0 16px;">
                    @foreach ($items as $item)
                        @php($percent = $split->percentFor((int) $item['product_id']))
                        <li style="display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; border-bottom: 1px solid var(--ksm-line);">
                            <span>
                                {{ $item['name'] }} &times; {{ $item['quantity'] }}
                                @if ($percent > 0)
                                    <br><small class="ksm-muted">{{ $percent }}% in KMoney</small>
                                @endif
                            </span>
                            <span>{{ $money($item['price'] * $item['quantity']) }}</span>
                        </li>
                    @endforeach
                </ul>

                <p style="display: flex; justify-content: space-between;">
                    <span>Totale merce</span><span>{{ $money($subtotal) }}</span>
                </p>
                <p style="display: flex; justify-content: space-between;">
                    <span>
                        Spedizione
                        @if ($split->shippingPercent > 0)
                            <br><small class="ksm-muted">{{ $split->shippingPercent }}% in KMoney, la quota più bassa del carrello</small>
                        @endif
                    </span>
                    <span>{{ $money($shipping) }}</span>
                </p>
                <p class="ksm-product__price" style="display: flex; justify-content: space-between; font-size: 1.2rem;">
                    <span>Totale</span><span>{{ $money($subtotal + $shipping) }}</span>
                </p>

                @if ($split->hasKmoney())
                    <p style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span>In KMoney</span><strong>{{ $ky($split->kmoney()) }}</strong>
                    </p>
                    <p style="display: flex; justify-content: space-between; margin-top: 0;">
                        <span>In euro</span><strong>{{ $money($split->euro()) }}</strong>
                    </p>
                @endif
            </aside>
        </div>
    </section>
@endsection
