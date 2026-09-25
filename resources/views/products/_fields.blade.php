{{--
    Scheda prodotto, uguale per area azienda e amministrazione.
    Vuole $product, $categories, $brands, $inDebt, $kmoneySteps e $cancelUrl;
    il <form> lo apre la pagina che la include.
--}}
{{-- Forme a blocco: la forma breve su una riga, prima di un blocco, confonde il compilatore di Blade. --}}
@php
    // Vuoto: la quota viene dalla categoria o dal contratto KMoney.
    $kmoneyChoice = old('kmoney_discount_percent', $product->kmoney_discount_percent === null ? '' : (int) $product->kmoney_discount_percent);

    // Varianti: si salvano solo se il tipo e' "Con varianti".
    $variantRows = array_values(old('variants', $product->variants->map(fn ($variant) => [
        'id' => $variant->id,
        'type' => $variant->variant_type,
        'value' => $variant->variant_value,
        'price' => $variant->variant_price,
        'stock' => $variant->variant_stock,
        'sku' => $variant->variant_sku,
    ])->all()));

    // Di norma il prodotto e' sempre disponibile: la quantita' si mostra solo se l'azienda gestisce la disponibilita'.
    $manageStock = (bool) old('manage_stock', $product->exists
        && ($product->stock !== null || $product->variants->contains(fn ($variant) => filled($variant->variant_stock))));

    // Tre righe libere per aggiungere varianti senza script.
    for ($blank = 0; $blank < 3; $blank++) {
        $variantRows[] = [];
    }
@endphp

<div class="ksm-editor">
    <div class="ksm-editor__main">
        <section class="ksm-box">
            <div class="ksm-box__head"><h2>Informazioni</h2></div>

            <div class="ksm-formgrid ksm-formgrid--2">
                <div class="ksm-field">
                    <label class="ksm-label" for="name">Nome</label>
                    <input class="ksm-input" id="name" name="name" value="{{ old('name', $product->name) }}" required>
                    @error('name')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="sku">Codice prodotto (facoltativo)</label>
                    <input class="ksm-input" id="sku" name="sku" value="{{ old('sku', $product->sku) }}" maxlength="100">
                    @error('sku')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
                <div class="ksm-field ksm-field--wide">
                    <label class="ksm-label" for="short_description">Descrizione breve</label>
                    <input class="ksm-input" id="short_description" name="short_description" maxlength="500"
                           value="{{ old('short_description', $product->short_description) }}">
                    <small class="ksm-muted">Una riga che compare sotto il nome, nel catalogo e in cima alla scheda.</small>
                    @error('short_description')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="description">Descrizione</label>
                <textarea class="ksm-textarea" id="description" name="description" data-richtext rows="10">{{ old('description', $product->description) }}</textarea>
                @include('partials.richtext-assets')
                @error('description')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>
        </section>

        <section class="ksm-box">
            <div class="ksm-box__head"><h2>Prezzo e disponibilità</h2></div>

            <div class="ksm-formgrid">
                <div class="ksm-field">
                    <label class="ksm-label" for="price">Prezzo (€)</label>
                    <input class="ksm-input" id="price" name="price" type="number" step="0.01" min="0"
                           value="{{ old('price', $product->price) }}" required>
                    @error('price')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="discount_price">Prezzo scontato (€)</label>
                    <input class="ksm-input" id="discount_price" name="discount_price" type="number" step="0.01" min="0"
                           value="{{ old('discount_price', $product->discount_price) }}">
                    <small class="ksm-muted">Vuoto = nessuno sconto.</small>
                    @error('discount_price')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" style="display: flex; gap: 8px; align-items: center;">
                        <input type="hidden" name="manage_stock" value="0">
                        <input id="manage_stock" name="manage_stock" type="checkbox" value="1" @checked($manageStock)>
                        Gestisci la disponibilità
                    </label>
                    <small class="ksm-muted">Se non la gestisci il prodotto è sempre disponibile.</small>
                </div>
                <div class="ksm-field" data-stock-field @unless ($manageStock) hidden @endunless>
                    <label class="ksm-label" for="stock">Quantità disponibile</label>
                    <input class="ksm-input" id="stock" name="stock" type="number" min="0"
                           value="{{ old('stock', $product->stock) }}">
                    <small class="ksm-muted">0 = esaurito. Con le varianti conta quella di ogni variante.</small>
                    @error('stock')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="weight_kg">Peso (kg)</label>
                    <input class="ksm-input" id="weight_kg" name="weight_kg" type="number" step="0.01" min="0"
                           value="{{ old('weight_kg', $product->weight_kg) }}">
                    @error('weight_kg')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>
            </div>
        </section>

        <section class="ksm-box">
            <div class="ksm-box__head"><h2>Varianti</h2></div>
            <p class="ksm-box__hint">
                Valgono solo se il tipo del prodotto è «Con varianti». Una riga per variante, per esempio Taglia e M;
                per toglierne una svuota tipo e valore. Prezzo vuoto = prezzo del prodotto.
                <span data-stock-field @unless ($manageStock) hidden @endunless>Disponibilità vuota = sempre disponibile.</span>
            </p>
            @error('variants')<p class="ksm-error">{{ $message }}</p>@enderror

            <div class="ksm-table-wrap">
                <table class="ksm-table ksm-table--inputs">
                    <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Valore</th>
                        <th>Prezzo (€)</th>
                        <th data-stock-field @unless ($manageStock) hidden @endunless>Disponibilità</th>
                        <th>Codice</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($variantRows as $i => $row)
                        <tr>
                            <td>
                                <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
                                <input class="ksm-input" name="variants[{{ $i }}][type]" value="{{ $row['type'] ?? '' }}"
                                       placeholder="{{ $loop->first ? 'Taglia' : '' }}" aria-label="Tipo della variante {{ $i + 1 }}">
                            </td>
                            <td>
                                <input class="ksm-input" name="variants[{{ $i }}][value]" value="{{ $row['value'] ?? '' }}"
                                       placeholder="{{ $loop->first ? 'M' : '' }}" aria-label="Valore della variante {{ $i + 1 }}">
                            </td>
                            <td>
                                <input class="ksm-input" name="variants[{{ $i }}][price]" type="number" step="0.01" min="0"
                                       value="{{ $row['price'] ?? '' }}" aria-label="Prezzo della variante {{ $i + 1 }}">
                            </td>
                            <td data-stock-field @unless ($manageStock) hidden @endunless>
                                <input class="ksm-input" name="variants[{{ $i }}][stock]" type="number" min="0"
                                       value="{{ $row['stock'] ?? '' }}" aria-label="Disponibilità della variante {{ $i + 1 }}">
                            </td>
                            <td>
                                <input class="ksm-input" name="variants[{{ $i }}][sku]" value="{{ $row['sku'] ?? '' }}"
                                       aria-label="Codice della variante {{ $i + 1 }}">
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <aside class="ksm-editor__side">
        <section class="ksm-box">
            <div class="ksm-box__head"><h2>Pubblicazione</h2></div>

            <div class="ksm-field">
                <label class="ksm-label" for="status">Stato</label>
                <select class="ksm-select" id="status" name="status">
                    <option value="active" @selected(old('status', $product->status ?? 'active') === 'active')>Attivo, visibile sul sito</option>
                    <option value="inactive" @selected(old('status', $product->status) === 'inactive')>Non attivo, nascosto</option>
                </select>
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="product_type">Tipo</label>
                <select class="ksm-select" id="product_type" name="product_type">
                    <option value="simple" @selected(old('product_type', $product->product_type) === 'simple')>Semplice</option>
                    <option value="variable" @selected(old('product_type', $product->product_type) === 'variable')>Con varianti</option>
                </select>
            </div>
        </section>

        <section class="ksm-box">
            <div class="ksm-box__head"><h2>Organizzazione</h2></div>

            <div class="ksm-field">
                <label class="ksm-label" for="category_id">Categoria</label>
                <select class="ksm-select" id="category_id" name="category_id">
                    <option value="">Nessuna</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                @error('category_id')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="brand_id">Marca</label>
                <select class="ksm-select" id="brand_id" name="brand_id">
                    <option value="">Nessuna</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(old('brand_id', $product->brand_id) == $brand->id)>
                            {{ $brand->name }}
                        </option>
                    @endforeach
                </select>
                @error('brand_id')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>
        </section>

        <section class="ksm-box">
            <div class="ksm-box__head"><h2>Immagine principale</h2></div>

            @if ($product->featured_image)
                <img class="ksm-media-current" src="{{ asset('storage/'.$product->featured_image) }}" alt="Immagine attuale">
            @else
                <div class="ksm-media-empty">Nessuna immagine</div>
            @endif

            <div class="ksm-field">
                <label class="ksm-label" for="featured_image">{{ $product->featured_image ? 'Sostituisci immagine' : 'Carica immagine' }}</label>
                @include('partials.image-editor-assets')
                <input class="ksm-input" id="featured_image" name="featured_image" type="file"
                       accept="image/jpeg,image/png,image/webp,image/gif"
                       data-image-editor data-ratios="1:1,4:3,free" data-max="1600x1600">
                <small class="ksm-muted">Nel catalogo le foto sono quadrate. Dopo la scelta puoi ritagliare, spostare e ruotare.</small>
                @error('featured_image')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>
        </section>

        <section class="ksm-box">
            <div class="ksm-box__head"><h2>KMoney</h2></div>

            <div class="ksm-field">
                <label class="ksm-label" for="kmoney_discount_percent">Quota pagata in KMoney</label>
                <select class="ksm-select" id="kmoney_discount_percent" name="kmoney_discount_percent" @disabled($inDebt)>
                    <option value="">
                        Automatica{{ $product->exists ? ', oggi '.$product->kmoney_percent.'%' : ', da categoria o contratto' }}
                    </option>
                    @foreach ($kmoneySteps as $step)
                        <option value="{{ $step }}" @selected((string) $kmoneyChoice === (string) $step)>{{ $step }}%</option>
                    @endforeach
                </select>
                <small class="ksm-muted">
                    {{ $inDebt
                        ? 'Il conto KMoney è in debito: la quota è al 100% finché non torna in positivo.'
                        : 'La parte del prezzo che il cliente paga in KMoney; il resto lo paga in euro.' }}
                </small>
                @error('kmoney_discount_percent')<span class="ksm-error">{{ $message }}</span>@enderror
            </div>
        </section>
    </aside>

    <div class="ksm-savebar">
        <span class="ksm-savebar__note">{{ $product->exists ? 'Le modifiche sono visibili sul sito appena salvi.' : 'Il prodotto compare sul sito se è attivo.' }}</span>
        <a class="ksm-btn ksm-btn--ghost" href="{{ $cancelUrl }}">Annulla</a>
        <button class="ksm-btn ksm-btn--primary" type="submit">{{ $product->exists ? 'Salva le modifiche' : 'Crea prodotto' }}</button>
    </div>
</div>

<script>
    // La quantita' compare solo se l'azienda gestisce la disponibilita'.
    (function () {
        var toggle = document.getElementById('manage_stock');
        if (!toggle) return;
        toggle.addEventListener('change', function () {
            document.querySelectorAll('[data-stock-field]').forEach(function (el) {
                el.hidden = !toggle.checked;
            });
        });
    })();
</script>
