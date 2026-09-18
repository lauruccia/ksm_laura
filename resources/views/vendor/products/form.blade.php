@extends('layouts.panel')

@section('title', 'Prodotto · KSM')
@section('role', 'Area azienda')
@section('nav')@include('vendor.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>{{ $product->exists ? 'Modifica prodotto' : 'Nuovo prodotto' }}</h1>
        <a class="ksm-btn ksm-btn--ghost" href="{{ route('vendor.products.index') }}">Torna all'elenco</a>
    </div>

    <form class="ksm-card" style="padding: 24px; max-width: 820px;" method="POST"
          action="{{ $product->exists ? route('vendor.products.update', $product) : route('vendor.products.store') }}"
          enctype="multipart/form-data">
        @csrf
        @if ($product->exists)
            @method('PUT')
        @endif

        <div class="ksm-field">
            <label class="ksm-label" for="name">Nome</label>
            <input class="ksm-input" id="name" name="name" value="{{ old('name', $product->name) }}" required>
        </div>

        <div class="ksm-grid ksm-grid--2">
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
            </div>
        </div>

        <div class="ksm-field">
            <label class="ksm-label" for="short_description">Descrizione breve</label>
            <input class="ksm-input" id="short_description" name="short_description"
                   value="{{ old('short_description', $product->short_description) }}">
        </div>

        <div class="ksm-field">
            <label class="ksm-label" for="description">Descrizione</label>
            <textarea class="ksm-textarea" id="description" name="description" data-richtext rows="8">{{ old('description', $product->description) }}</textarea>
            @include('partials.richtext-assets')
            @error('description')<span class="ksm-error">{{ $message }}</span>@enderror
        </div>

        <div class="ksm-grid ksm-grid--3">
            <div class="ksm-field">
                <label class="ksm-label" for="price">Prezzo</label>
                <input class="ksm-input" id="price" name="price" type="number" step="0.01"
                       value="{{ old('price', $product->price) }}" required>
            </div>
            <div class="ksm-field">
                <label class="ksm-label" for="discount_price">Prezzo scontato</label>
                <input class="ksm-input" id="discount_price" name="discount_price" type="number" step="0.01"
                       value="{{ old('discount_price', $product->discount_price) }}">
            </div>
            <div class="ksm-field">
                <label class="ksm-label" for="stock">Giacenza (facoltativa)</label>
                <input class="ksm-input" id="stock" name="stock" type="number" min="0"
                       value="{{ old('stock', $product->stock) }}" placeholder="Stock non gestito">
                <small class="ksm-muted">Vuoto = disponibile senza gestione stock. 0 = esaurito. Per i prodotti con varianti vale la giacenza di ciascuna variante.</small>
            </div>
        </div>

        {{-- Vuoto: la quota viene dalla categoria o dal contratto KMoney. --}}
        {{-- Forma a blocco: la forma breve su una riga, prima del blocco delle varianti, confonde il compilatore di Blade. --}}
        @php
            $kmoneyChoice = old('kmoney_discount_percent', $product->kmoney_discount_percent === null ? '' : (int) $product->kmoney_discount_percent);
        @endphp
        <div class="ksm-field">
            <label class="ksm-label" for="kmoney_discount_percent">Quota pagata in KMoney</label>
            <select class="ksm-select" id="kmoney_discount_percent" name="kmoney_discount_percent" @disabled($inDebt)>
                <option value="">
                    Automatica{{ $product->exists ? ', oggi '.$product->kmoney_percent.'%' : ', da categoria o contratto' }}
                </option>
                @foreach (\App\Payments\KMoney\KMoneyShare::STEPS as $step)
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

        <div class="ksm-grid ksm-grid--3">
            <div class="ksm-field">
                <label class="ksm-label" for="weight_kg">Peso in kg</label>
                <input class="ksm-input" id="weight_kg" name="weight_kg" type="number" step="0.01"
                       value="{{ old('weight_kg', $product->weight_kg) }}">
            </div>
            <div class="ksm-field">
                <label class="ksm-label" for="product_type">Tipo</label>
                <select class="ksm-select" id="product_type" name="product_type">
                    <option value="simple" @selected(old('product_type', $product->product_type) === 'simple')>Semplice</option>
                    <option value="variable" @selected(old('product_type', $product->product_type) === 'variable')>Con varianti</option>
                </select>
            </div>
            <div class="ksm-field">
                <label class="ksm-label" for="status">Stato</label>
                <select class="ksm-select" id="status" name="status">
                    <option value="active" @selected(old('status', $product->status) === 'active')>Attivo</option>
                    <option value="inactive" @selected(old('status', $product->status) === 'inactive')>Non attivo</option>
                </select>
            </div>
        </div>

        {{-- Varianti: si salvano solo se il tipo e' "Con varianti". --}}
        @php
            $variantRows = array_values(old('variants', $product->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'type' => $variant->variant_type,
                'value' => $variant->variant_value,
                'price' => $variant->variant_price,
                'stock' => $variant->variant_stock,
                'sku' => $variant->variant_sku,
            ])->all()));

            // Tre righe libere per aggiungere varianti senza script.
            for ($blank = 0; $blank < 3; $blank++) {
                $variantRows[] = [];
            }
        @endphp

        <fieldset class="ksm-field" style="border: 1px solid var(--ksm-line); border-radius: var(--ksm-radius); padding: 16px;">
            <legend class="ksm-label" style="padding: 0 6px;">Varianti</legend>
            <p class="ksm-muted" style="font-size: .85rem; margin-top: 0;">
                Una riga per variante, per esempio Taglia e M. Per togliere una variante svuota tipo e valore.
                Le varianti valgono solo se il tipo del prodotto è "Con varianti".
                Prezzo vuoto = prezzo del prodotto. Disponibilità vuota = stock non gestito; 0 = variante esaurita.
            </p>
            @error('variants')<p class="ksm-error">{{ $message }}</p>@enderror

            <div class="ksm-table-wrap">
                <table class="ksm-table">
                    <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Valore</th>
                        <th>Prezzo</th>
                        <th>Disponibilità</th>
                        <th>Codice</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($variantRows as $i => $row)
                        <tr>
                            <td>
                                <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
                                <input class="ksm-input" name="variants[{{ $i }}][type]" value="{{ $row['type'] ?? '' }}"
                                       aria-label="Tipo della variante {{ $i + 1 }}">
                            </td>
                            <td>
                                <input class="ksm-input" name="variants[{{ $i }}][value]" value="{{ $row['value'] ?? '' }}"
                                       aria-label="Valore della variante {{ $i + 1 }}">
                            </td>
                            <td>
                                <input class="ksm-input" name="variants[{{ $i }}][price]" type="number" step="0.01" min="0"
                                       value="{{ $row['price'] ?? '' }}" aria-label="Prezzo della variante {{ $i + 1 }}">
                            </td>
                            <td>
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
        </fieldset>

        <div class="ksm-field">
            <label class="ksm-label" for="featured_image">Immagine principale</label>
            @if ($product->featured_image)
                <img class="ksm-image-current" src="{{ asset('storage/'.$product->featured_image) }}" alt="">
            @endif
            @include('partials.image-editor-assets')
            <input class="ksm-input" id="featured_image" name="featured_image" type="file"
                   accept="image/jpeg,image/png,image/webp,image/gif"
                   data-image-editor data-ratios="1:1,4:3,free" data-max="1600x1600">
            <small class="ksm-muted">Nel catalogo le foto sono quadrate. Dopo la scelta puoi ritagliare, spostare e ruotare: il file viene alleggerito prima dell'invio.</small>
            @error('featured_image')<span class="ksm-error">{{ $message }}</span>@enderror
        </div>

        <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
    </form>
@endsection
