@props(['product'])

<article class="ksm-card ksm-product">
    <a class="ksm-product__thumb @if (! $product->featured_image) ksm-product__thumb--empty @endif"
       href="{{ route('products.show', $product->slug) }}"
       @if ($product->featured_image) style="background-image: url('{{ asset('storage/'.$product->featured_image) }}')" @endif
       aria-label="{{ $product->name }}">
        @if (! $product->featured_image)
            <x-icon name="box" :size="26" />
        @endif
    </a>

    <div class="ksm-product__body">
        <h3 class="ksm-card__title">
            <a href="{{ route('products.show', $product->slug) }}">{{ $product->name }}</a>
        </h3>

        <p class="ksm-product__company">{{ $product->company->name }}</p>

        <div class="ksm-product__prices">
            <span class="ksm-product__price">{{ \App\Support\Money::format($product->final_price) }}</span>
            @if ($product->final_price < (float) $product->price)
                <s class="ksm-product__old">{{ \App\Support\Money::format((float) $product->price) }}</s>
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

    <form method="POST" action="{{ route('cart.add', $product->slug) }}">
        @csrf
        <button class="ksm-btn ksm-btn--primary" type="submit" @disabled(! $product->isInStock())>
            <x-icon name="cart" :size="17" />
            {{ __('site.add_to_cart') }}
        </button>
    </form>
</article>
