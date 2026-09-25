@props(['product', 'storefront' => false])

@php
    $hasImage = $product->featured_image && \Illuminate\Support\Facades\Storage::disk('public')->exists($product->featured_image);
@endphp

<article class="ksm-card ksm-product">
    <a class="ksm-product__thumb @if (! $hasImage) ksm-product__thumb--empty @endif"
       href="{{ route('products.show', $product->slug) }}"
       aria-label="{{ $product->name }}">
        {{-- Un'immagine vera e non uno sfondo: il browser la scarica solo quando la scheda arriva a schermo. --}}
        @if ($hasImage)
            <img class="ksm-product__img" src="{{ asset('storage/'.\App\Support\Images\ImageStore::thumb($product->featured_image)) }}"
                 alt="" loading="lazy" decoding="async" width="600" height="600">
        @else
            <x-icon name="box" :size="26" />
        @endif
        @if ($storefront)
            {{-- Un segno solo, il piu' utile a chi compra: offerta, poi piu' venduto, poi novita'. --}}
            @if ($product->final_price < (float) $product->price)
                <span class="ksm-store-sale">{{ __('storefront.sale') }}</span>
            @elseif ($product->is_bestseller)
                <span class="ksm-store-sale ksm-store-sale--best">{{ __('storefront.bestseller') }}</span>
            @elseif ($product->created_at?->gt(now()->subDays(\App\Http\Controllers\ProductController::NEW_FOR_DAYS)))
                <span class="ksm-store-sale ksm-store-sale--new">{{ __('storefront.new') }}</span>
            @endif
        @endif
    </a>

    <div class="ksm-product__body">
        <h3 class="ksm-card__title">
            <a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a>
        </h3>

        @php
            // Nello shop la riga sotto il nome e' la descrizione breve, se c'e'; altrimenti chi vende.
            $subtitle = $storefront ? \Illuminate\Support\Str::limit(trim(strip_tags((string) $product->short_description)), 60) : '';
        @endphp
        <p class="ksm-product__company">{{ $subtitle !== '' ? $subtitle : $product->company->name }}</p>

        @if ($storefront && (int) $product->reviews_count > 0)
            <p class="ksm-product__rating" aria-label="{{ number_format((float) $product->reviews_avg_rating, 1, ',', '') }} / 5 · {{ __('storefront.reviews', ['count' => $product->reviews_count]) }}">
                @for ($star = 1; $star <= 5; $star++)
                    <x-icon name="star" :size="14" :class="$star <= round((float) $product->reviews_avg_rating) ? 'is-on' : ''" />
                @endfor
                <span>({{ $product->reviews_count }})</span>
            </p>
        @endif

        <div class="ksm-product__prices">
            @if ($product->product_type !== 'variable' && $product->final_price < (float) $product->price)
                <s class="ksm-product__old">{{ \App\Support\Money::format((float) $product->price) }}</s>
            @endif
            <span class="ksm-product__price">{{ $product->display_price }}</span>
            @if ($storefront && $product->weight_label)
                <small class="ksm-product__weight">({{ $product->weight_label }})</small>
            @endif
        </div>

        {{-- La quota effettiva, ricalcolata da contratto, categoria e scelta sul prodotto. --}}
        @if ($product->kmoney_percent || ! $product->isInStock())
            <div class="ksm-product__tags">
                @if ($product->kmoney_percent)
                    <span class="ksm-badge">
                        <x-icon name="sparkle" :size="14" />
                        {{ __('site.kmoney_share', ['percent' => (int) $product->kmoney_percent]) }}
                    </span>
                @endif
                @unless ($product->isInStock())
                    <span class="ksm-badge ksm-badge--muted">{{ __('site.shop_out_of_stock') }}</span>
                @endunless
            </div>
        @endif
    </div>

    <form method="{{ $product->product_type === 'variable' ? 'GET' : 'POST' }}" action="{{ route($product->product_type === 'variable' ? 'products.show' : 'cart.add', $product->slug) }}">
        @if ($product->product_type !== 'variable') @csrf @endif
        <button class="ksm-btn ksm-btn--primary" type="submit" aria-label="{{ $product->product_type === 'variable' ? __('storefront.choose_variant') : __('site.add_to_cart') }}: {{ $product->name }}" @disabled(! $product->isInStock())>
            <x-icon :name="$product->product_type === 'variable' ? 'sliders' : 'cart'" :size="17" />
            <span @class(['ksm-store-cart-label' => $storefront])>{{ $product->product_type === 'variable' ? __('storefront.choose_variant') : __('site.add_to_cart') }}</span>
        </button>
    </form>
</article>

