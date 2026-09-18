@extends('layouts.checkout')

@section('title', 'Pagamento')

@section('content')
    @php
        $money = fn ($value) => \App\Support\Money::format($value);
        $ky = fn ($value) => number_format((float) $value, 2, ',', '.').' KY';
        $blocked = ($split->hasEuro() && empty($methods))
            || ($split->hasKmoney() && ! $kmoneyAvailable && ! $euroFallback);
        $total = $subtotal + $shipping;
        $pieces = (int) $items->sum('quantity');

        $countries = ['Italia', 'San Marino', 'Città del Vaticano', 'Svizzera', 'Austria', 'Belgio', 'Croazia',
            'Francia', 'Germania', 'Grecia', 'Irlanda', 'Lussemburgo', 'Malta', 'Paesi Bassi', 'Polonia',
            'Portogallo', 'Regno Unito', 'Slovenia', 'Spagna'];
        $country = old('billing_country', $defaults['billing_country'] ?? null) ?: 'Italia';
        if (! in_array($country, $countries, true)) {
            array_unshift($countries, $country);
        }

        $chosenMethod = old('method', $methods[0] ?? null);
        $kmoneyChoice = old('pagamento_kmoney', 'conto');
    @endphp

    <div class="ksm-co" data-checkout>
        {{-- Su telefono il riepilogo sta chiuso in cima, con il totale sempre in vista. --}}
        <button class="ksm-co-toggle" type="button" data-summary-toggle aria-controls="riepilogo" aria-expanded="true">
            <span class="ksm-co-toggle__label">
                <x-icon name="cart" :size="19" />
                <span data-summary-label>Nascondi riepilogo ordine</span>
                <svg class="ksm-co-toggle__chevron" width="12" height="12" viewBox="0 0 12 12" aria-hidden="true"><path d="M2 4l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
            </span>
            <strong>{{ $money($total) }}</strong>
        </button>

        <main class="ksm-co-main">
            <div class="ksm-co-main__inner">
                <nav class="ksm-co-crumbs" aria-label="Passaggi">
                    <a href="{{ route('cart.index') }}">Carrello</a>
                    <span aria-hidden="true">›</span>
                    <span @class(['is-current' => auth()->guest()])>Account</span>
                    <span aria-hidden="true">›</span>
                    <span @class(['is-current' => auth()->check()])>Consegna e pagamento</span>
                </nav>

                @if (session('success'))
                    <div class="ksm-co-alert ksm-co-alert--success">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="ksm-co-alert ksm-co-alert--error">{{ session('error') }}</div>
                @endif

                @guest
                    @include('pages.checkout.partials.guest')
                @else
                    <form method="POST" action="{{ route('logout') }}" id="checkout-logout" hidden>@csrf</form>

                    <form method="POST" action="{{ route('checkout.process') }}" novalidate>
                        @csrf

                        <section class="ksm-co-section">
                            <div class="ksm-co-section__head">
                                <h2>Contatto</h2>
                                <p>{{ auth()->user()->name }} · <button type="submit" form="checkout-logout" class="ksm-co-link">Esci</button></p>
                            </div>

                            <div class="ksm-co-stack">
                                <x-checkout.field name="billing_email" type="email" label="Email per la conferma d'ordine"
                                                  :value="old('billing_email', $defaults['billing_email'] ?? '')" autocomplete="email" required />
                            </div>
                        </section>

                        <section class="ksm-co-section">
                            <div class="ksm-co-section__head"><h2>Consegna</h2></div>

                            <div class="ksm-co-stack">
                                <div @class(['ksm-co-field', 'ksm-co-field--select', 'has-error' => $errors->has('billing_country')])>
                                    <select id="billing_country" name="billing_country" autocomplete="country-name">
                                        @foreach ($countries as $option)
                                            <option value="{{ $option }}" @selected($country === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                    <label for="billing_country">Paese</label>
                                    @error('billing_country')<p class="ksm-co-field__error">{{ $message }}</p>@enderror
                                </div>

                                <x-checkout.field name="billing_name" label="Nome e cognome"
                                                  :value="old('billing_name', $defaults['billing_name'] ?? '')" autocomplete="name" required />

                                <x-checkout.field name="billing_address" label="Indirizzo e numero civico"
                                                  :value="old('billing_address', $defaults['billing_address'] ?? '')" autocomplete="street-address" required />

                                <div class="ksm-co-row ksm-co-row--3">
                                    <x-checkout.field name="billing_zip" label="CAP" inputmode="numeric"
                                                      :value="old('billing_zip', $defaults['billing_zip'] ?? '')" autocomplete="postal-code" required />
                                    <x-checkout.field name="billing_city" label="Città"
                                                      :value="old('billing_city', $defaults['billing_city'] ?? '')" autocomplete="address-level2" required />
                                    <x-checkout.field name="billing_state" label="Provincia" maxlength="120"
                                                      :value="old('billing_state', $defaults['billing_state'] ?? '')" autocomplete="address-level1" />
                                </div>

                                <x-checkout.field name="billing_phone" type="tel" label="Telefono, per il corriere (facoltativo)"
                                                  :value="old('billing_phone', $defaults['billing_phone'] ?? '')" autocomplete="tel" />

                                <label class="ksm-co-check">
                                    <input type="checkbox" name="salva_dati" value="1" @checked(session()->hasOldInput() ? old('salva_dati') : true)>
                                    Salva questi dati per la prossima volta
                                </label>

                                <details class="ksm-co-notes" @if (old('notes')) open @endif>
                                    <summary>Aggiungi una nota per il venditore</summary>
                                    <div class="ksm-co-field ksm-co-field--textarea">
                                        <textarea id="notes" name="notes" rows="3" maxlength="1000" placeholder=" ">{{ old('notes') }}</textarea>
                                        <label for="notes">Orari di consegna, citofono, richieste…</label>
                                    </div>
                                    @error('notes')<p class="ksm-co-field__error">{{ $message }}</p>@enderror
                                </details>
                            </div>
                        </section>

                        <section class="ksm-co-section">
                            <div class="ksm-co-section__head"><h2>Spedizione</h2></div>

                            <div class="ksm-co-options">
                                <div class="ksm-co-option is-static">
                                    <span class="ksm-co-option__text">
                                        <strong>Spedizione standard</strong>
                                        <small>A cura di {{ $company->name }}</small>
                                    </span>
                                    <strong>{{ $shipping > 0 ? $money($shipping) : 'Gratis' }}</strong>
                                </div>
                            </div>
                        </section>

                        {{-- La quota KMoney si paga per prima, sul sito KMoney. --}}
                        @if ($split->hasKmoney())
                            <section class="ksm-co-section">
                                <div class="ksm-co-section__head"><h2>KMoney</h2></div>

                                <div class="ksm-co-options">
                                    <label class="ksm-co-option">
                                        <input type="radio" name="pagamento_kmoney" value="conto" @checked($kmoneyChoice === 'conto')>
                                        <span class="ksm-co-option__text">
                                            <strong>Ho un conto KMoney</strong>
                                            <small>
                                                Pago {{ $ky($split->kmoney()) }} in KMoney
                                                @if ($split->hasEuro()) e subito dopo {{ $money($split->euro()) }} in euro @endif
                                            </small>
                                        </span>
                                        <strong>{{ $ky($split->kmoney()) }}</strong>
                                    </label>

                                    @if ($euroFallback)
                                        <label class="ksm-co-option">
                                            <input type="radio" name="pagamento_kmoney" value="euro" @checked($kmoneyChoice === 'euro')>
                                            <span class="ksm-co-option__text">
                                                <strong>Non ho un conto KMoney</strong>
                                                <small>Pago tutto in euro</small>
                                            </span>
                                            <strong>{{ $money($total) }}</strong>
                                        </label>
                                    @endif
                                </div>

                                @if (! $euroFallback && $split->vendorInDebt)
                                    <p class="ksm-co-note">Questo venditore accetta questi prodotti solo in KMoney.</p>
                                @endif

                                @unless ($kmoneyAvailable)
                                    <div class="ksm-co-alert ksm-co-alert--error">Il venditore non ha ancora collegato il suo conto KMoney.</div>
                                @endunless

                                @error('pagamento_kmoney')<p class="ksm-co-field__error">{{ $message }}</p>@enderror
                            </section>
                        @endif

                        <section class="ksm-co-section">
                            <div class="ksm-co-section__head">
                                <h2>Pagamento</h2>
                            </div>
                            <p class="ksm-co-note ksm-co-note--secure">
                                <x-icon name="shield" :size="16" />
                                {{ $split->hasKmoney() ? 'La parte in euro si paga' : 'Il pagamento si completa' }} sul sito sicuro del gestore, poi torni qui.
                            </p>

                            @if (empty($methods))
                                <div class="ksm-co-alert ksm-co-alert--error">Questa azienda non ha ancora attivato un metodo di pagamento in euro.</div>
                            @else
                                <div class="ksm-co-options">
                                    @foreach ($methods as $method)
                                        <label class="ksm-co-option">
                                            <input type="radio" name="method" value="{{ $method }}" @checked($chosenMethod === $method)>
                                            <span class="ksm-co-option__text">
                                                <strong>{{ \App\Payments\GatewayManager::label($method) }}</strong>
                                            </span>
                                            <span class="ksm-co-option__detail">
                                                Dopo "Paga ora" ti portiamo su {{ match ($method) { 'stripe' => 'Stripe', 'paypal' => 'PayPal', default => \App\Payments\GatewayManager::label($method) } }}
                                                per completare l'acquisto in sicurezza.
                                            </span>
                                        </label>
                                    @endforeach
                                </div>

                                @if ($split->hasKmoney() && ! $split->hasEuro())
                                    <p class="ksm-co-note">Tutto l'ordine si paga in KMoney: il metodo in euro serve solo se scegli di pagare tutto in euro.</p>
                                @endif
                            @endif

                            @error('method')<p class="ksm-co-field__error">{{ $message }}</p>@enderror
                        </section>

                        <div class="ksm-co-actions">
                            <button class="ksm-co-btn ksm-co-btn--pay" type="submit" @disabled($blocked)>Paga ora</button>
                            <a class="ksm-co-back" href="{{ route('cart.index') }}">‹ Torna al carrello</a>
                        </div>
                    </form>
                @endguest

                <footer class="ksm-co-footer">
                    <span>© {{ date('Y') }} {{ $tenant->brandName() }}</span>
                    <a href="{{ route('contact') }}">Assistenza</a>
                    <a href="{{ route('orders.track') }}">Traccia un ordine</a>
                </footer>
            </div>
        </main>

        <aside class="ksm-co-summary" id="riepilogo" aria-label="Riepilogo ordine">
            <div class="ksm-co-summary__inner">
                <h2 class="ksm-co-visually-hidden">Riepilogo</h2>

                <ul class="ksm-co-lines">
                    @foreach ($items as $item)
                        @php
                            $percent = $split->percentFor((int) $item['product_id']);
                            $image = filled($item['image'] ?? null) && \Illuminate\Support\Facades\Storage::disk('public')->exists($item['image'])
                                ? asset('storage/'.$item['image'])
                                : null;
                        @endphp
                        <li class="ksm-co-line">
                            <span class="ksm-co-line__thumb">
                                @if ($image)
                                    <img src="{{ $image }}" alt="">
                                @else
                                    <span aria-hidden="true">{{ mb_substr($item['name'], 0, 1) }}</span>
                                @endif
                                <span class="ksm-co-line__qty" aria-label="Quantità">{{ $item['quantity'] }}</span>
                            </span>
                            <span class="ksm-co-line__name">
                                {{ $item['name'] }}
                                @if ($percent > 0)
                                    <small>{{ $percent }}% in KMoney</small>
                                @endif
                            </span>
                            <span class="ksm-co-line__price">{{ $money($item['price'] * $item['quantity']) }}</span>
                        </li>
                    @endforeach
                </ul>

                <dl class="ksm-co-totals">
                    <div>
                        <dt>Subtotale · {{ $pieces }} {{ $pieces === 1 ? 'articolo' : 'articoli' }}</dt>
                        <dd>{{ $money($subtotal) }}</dd>
                    </div>
                    <div>
                        <dt>
                            Spedizione
                            @if ($split->shippingPercent > 0)
                                <small>{{ $split->shippingPercent }}% in KMoney, la quota più bassa del carrello</small>
                            @endif
                        </dt>
                        <dd>{{ $shipping > 0 ? $money($shipping) : 'Gratis' }}</dd>
                    </div>
                    <div class="ksm-co-totals__grand">
                        <dt>Totale</dt>
                        <dd><small>{{ config('ksm.currency') }}</small> {{ $money($total) }}</dd>
                    </div>
                    @if ($split->hasKmoney())
                        <div class="ksm-co-totals__split">
                            <dt>di cui in KMoney</dt>
                            <dd>{{ $ky($split->kmoney()) }}</dd>
                        </div>
                        <div class="ksm-co-totals__split">
                            <dt>di cui in euro</dt>
                            <dd>{{ $money($split->euro()) }}</dd>
                        </div>
                    @endif
                </dl>

                <p class="ksm-co-seller">
                    <x-icon name="building" :size="16" />
                    Venduto e spedito da <a href="{{ route('companies.show', $company->slug) }}">{{ $company->name }}</a>
                </p>
            </div>
        </aside>
    </div>

    <script>
        // Riepilogo a comparsa su telefono. Senza script resta aperto.
        (function () {
            var root = document.querySelector('[data-checkout]');
            var toggle = root && root.querySelector('[data-summary-toggle]');
            if (!toggle) return;
            var label = toggle.querySelector('[data-summary-label]');
            var mobile = window.matchMedia('(max-width: 999px)');

            function set(open) {
                root.classList.toggle('is-summary-closed', !open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                label.textContent = open ? 'Nascondi riepilogo ordine' : 'Mostra riepilogo ordine';
            }

            set(!mobile.matches);
            toggle.addEventListener('click', function () {
                set(root.classList.contains('is-summary-closed'));
            });
        })();
    </script>
@endsection
