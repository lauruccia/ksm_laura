@extends('layouts.panel')

@section('title', ($order->exists ? 'Modifica '.$order->reference : 'Nuovo ordine').' · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    @php
        $field = fn (string $name) => old($name, $order->$name);
        // Righe salvate, poi qualche riga vuota per aggiungere prodotti.
        $rows = old('items', $order->items->map(fn ($item) => [
            'id' => $item->id, 'name' => $item->product_name, 'quantity' => $item->quantity, 'price' => $item->product_price,
        ])->all());
        $saved = count($order->items);
        if (! old('items')) {
            $rows = array_merge($rows, array_fill(0, $blankRows, []));
        }
    @endphp

    <div class="ksm-panel__head">
        <div>
            <h1>{{ $order->exists ? 'Modifica '.$order->reference : 'Nuovo ordine' }}</h1>
            <p class="ksm-panel__lead">
                Per <strong>{{ $company->name }}</strong>
                @unless ($order->exists) · <a href="{{ route('admin.orders.create') }}">cambia azienda</a> @endunless
            </p>
        </div>
        <div class="ksm-rowactions">
            <a class="ksm-btn ksm-btn--ghost" href="{{ $order->exists ? route('admin.orders.show', $order) : route('admin.orders.index') }}">Annulla</a>
        </div>
    </div>

    @if ($order->exists && $order->requiredPayments()->isNotEmpty())
        <p class="ksm-alert ksm-alert--info" role="status">
            Questo ordine ha pagamenti online: cambiare righe o spedizione cambia i totali dell'ordine, non quanto il cliente ha pagato.
        </p>
    @endif

    @if ($errors->any())
        <p class="ksm-alert ksm-alert--error" role="alert">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ $order->exists ? route('admin.orders.update', $order) : route('admin.orders.store') }}">
        @csrf
        @if ($order->exists)
            @method('PUT')
        @else
            <input type="hidden" name="company_id" value="{{ $company->id }}">
        @endif

        <section class="ksm-box">
            <div class="ksm-box__head"><h2>Prodotti</h2></div>

            @if (! $products && ! $saved)
                <p class="ksm-muted">L'azienda non ha prodotti: aggiungine uno prima di creare l'ordine.</p>
            @endif

            <div class="ksm-table-wrap">
                <table class="ksm-table">
                    <thead><tr><th>Prodotto</th><th style="width: 110px;">Quantità</th><th style="width: 150px;">Prezzo cad. (€)</th><th style="width: 80px;">Togli</th></tr></thead>
                    <tbody>
                    @foreach ($rows as $i => $row)
                        <tr>
                            <td>
                                @if (! empty($row['id']))
                                    <input type="hidden" name="items[{{ $i }}][id]" value="{{ $row['id'] }}">
                                    {{ $row['name'] ?? $order->items->firstWhere('id', $row['id'])?->product_name }}
                                @else
                                    <select class="ksm-select" name="items[{{ $i }}][product]" aria-label="Prodotto">
                                        <option value="">— aggiungi un prodotto —</option>
                                        @foreach ($products as $value => $label)
                                            <option value="{{ $value }}" @selected(($row['product'] ?? null) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                @error("items.$i.product")<span class="ksm-error">{{ $message }}</span>@enderror
                            </td>
                            <td>
                                <input class="ksm-input" type="number" min="1" name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? 1 }}" aria-label="Quantità">
                            </td>
                            <td>
                                <input class="ksm-input" type="number" min="0" step="0.01" name="items[{{ $i }}][price]" value="{{ $row['price'] ?? '' }}"
                                       placeholder="{{ empty($row['id']) ? 'prezzo di oggi' : '' }}" aria-label="Prezzo">
                            </td>
                            <td style="text-align: center;">
                                @if (! empty($row['id']))
                                    <input type="checkbox" name="items[{{ $i }}][remove]" value="1" aria-label="Togli la riga" @checked(! empty($row['remove']))>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <small class="ksm-muted">Le righe senza prodotto si ignorano. Col prezzo vuoto vale quello di oggi. La disponibilità dei prodotti si aggiorna da sola.</small>

            <div class="ksm-field" style="max-width: 220px; margin-top: 14px;">
                <label class="ksm-label" for="shipping">Spedizione (€)</label>
                <input class="ksm-input" id="shipping" name="shipping" type="number" min="0" step="0.01" value="{{ $field('shipping') }}">
            </div>
        </section>

        <section class="ksm-box">
            <div class="ksm-box__head"><h2>Cliente</h2></div>
            <div class="ksm-formgrid">
                @foreach ([
                    'billing_name' => 'Nome e cognome', 'billing_email' => 'Email', 'billing_phone' => 'Telefono',
                    'billing_address' => 'Indirizzo', 'billing_city' => 'Città', 'billing_zip' => 'CAP',
                    'billing_state' => 'Provincia', 'billing_country' => 'Paese',
                ] as $name => $label)
                    <div class="ksm-field">
                        <label class="ksm-label" for="{{ $name }}">{{ $label }}</label>
                        <input class="ksm-input" id="{{ $name }}" name="{{ $name }}" type="{{ $name === 'billing_email' ? 'email' : 'text' }}"
                               value="{{ $field($name) }}" @if (in_array($name, ['billing_name', 'billing_email'])) required @endif>
                        @error($name)<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                @endforeach
            </div>
            <div class="ksm-field">
                <label class="ksm-label" for="notes">Note del cliente</label>
                <textarea class="ksm-input" id="notes" name="notes" rows="2">{{ $field('notes') }}</textarea>
            </div>
            @unless ($order->exists)
                <small class="ksm-muted">Se esiste un account con questa email, l'ordine compare nella sua area cliente.</small>
            @endunless
        </section>

        @unless ($order->exists)
            <section class="ksm-box">
                <div class="ksm-box__head"><h2>Provenienza e stato</h2></div>
                <div class="ksm-formgrid">
                    <div class="ksm-field">
                        <label class="ksm-label" for="site">Sito</label>
                        <select class="ksm-select" id="site" name="site">
                            @foreach ($sites as $value => $label)
                                <option value="{{ $value }}" @selected(old('site', 'platform') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="ksm-muted">Il cliente ritrova l'ordine su questo sito.</small>
                        @error('site')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="ksm-field">
                        <label class="ksm-label" for="status">Stato</label>
                        <select class="ksm-select" id="status" name="status">
                            @foreach (['pending', 'paid', 'shipped', 'completed'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $order->status) === $status)>{{ \App\Models\Order::STATUS_LABELS[$status] }}</option>
                            @endforeach
                        </select>
                        <small class="ksm-muted">Da Pagato in poi la merce viene scalata dalla disponibilità.</small>
                    </div>
                </div>
            </section>
        @endunless

        <div class="ksm-savebar">
            <span class="ksm-savebar__note">{{ $order->exists ? 'Stato e spedizione si cambiano dalla scheda dell\'ordine.' : 'Nessuna email parte alla creazione.' }}</span>
            <button class="ksm-btn ksm-btn--primary" type="submit">{{ $order->exists ? 'Salva le modifiche' : 'Crea ordine' }}</button>
        </div>
    </form>
@endsection
