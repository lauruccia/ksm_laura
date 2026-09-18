@extends('layouts.app')

@section('title', $product->name)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/shop.css') }}?v={{ filemtime(public_path('css/shop.css')) }}">
@endpush

@section('content')
    <section class="ksm-section ksm-product-detail">
        <div class="ksm-container">
            <nav class="ksm-product-detail__breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('products.index') }}">{{ __('site.nav_products') }}</a>
                <x-icon name="arrow" :size="14" />
                @if ($product->category)
                    <a href="{{ route('products.index', ['categoria' => $product->category_id]) }}">{{ $product->category->name }}</a>
                    <x-icon name="arrow" :size="14" />
                @endif
                <span aria-current="page">{{ $product->name }}</span>
            </nav>
            <x-ad-slot placement="product_details_above_product_detail"
                       :city="$product->company->city" :category="$product->company->category_id" />

            <div class="ksm-grid ksm-grid--2">
                <div class="ksm-product-detail__image">
                    @if ($product->featured_image && \Illuminate\Support\Facades\Storage::disk('public')->exists($product->featured_image))
                        <img src="{{ asset('storage/'.$product->featured_image) }}" alt="{{ $product->name }}">
                    @else
                        <x-icon name="box" :size="100" />
                    @endif
                </div>

                <div class="ksm-product-detail__info">
                    <h1 style="font-size: 1.9rem;">{{ $product->name }}</h1>
                    <p class="ksm-muted">
                        @if ($product->company->hasPage())
                            <a href="{{ route('companies.show', $product->company->slug) }}">{{ $product->company->name }}</a>
                        @else
                            {{ $product->company->name }}
                        @endif
                    </p>

                    <p class="ksm-product__price" style="font-size: 1.6rem;">
                        {{ $product->display_price }}
                        @if ($product->product_type !== 'variable' && $product->final_price < (float) $product->price)
                            <s class="ksm-product__old">{{ \App\Support\Money::format((float) $product->price) }}</s>
                        @endif
                    </p>

                    @if ($product->short_description)
                        <p>{{ $product->short_description }}</p>
                    @endif

                    <p>
                        @if ($product->isInStock())
                            <span class="ksm-badge">Disponibile</span>
                        @else
                            <span class="ksm-badge ksm-badge--muted">Esaurito</span>
                        @endif
                    </p>

                    @if ($parkedCompany)
                        <p class="ksm-muted" style="margin: 0 0 12px;">
                            Nel carrello hai prodotti di <strong>{{ $parkedCompany->name }}</strong>.
                            Aggiungendo questo apri il carrello di {{ $product->company->name }}: l'altro resta in attesa,
                            lo ritrovi <a href="{{ route('cart.index') }}">nel carrello</a>.
                        </p>
                    @endif

                    <form method="POST" action="{{ route('cart.add', $product->slug) }}" style="display: flex; flex-wrap: wrap; gap: 10px;">
                        @csrf
                        @if ($product->product_type === 'variable')
                            <fieldset style="flex-basis: 100%; min-width: 0; border: 0; padding: 0; margin: 0 0 12px;">
                                <legend class="ksm-label">{{ __('storefront.choose_variant') }}</legend>
                                @foreach ($product->variants as $variant)
                                    <label style="display: flex; align-items: center; gap: 10px; padding: 12px 0; border-bottom: 1px solid var(--ksm-line);">
                                        <input type="radio" name="variant_id" value="{{ $variant->id }}" required @checked((string) old('variant_id') === (string) $variant->id) @disabled(! $variant->isInStock())>
                                        <span>{{ $variant->label() }} — <strong>{{ \App\Support\Money::format($variant->priceFor($product)) }}</strong>
                                            @unless ($variant->isInStock()) · {{ __('site.shop_out_of_stock') }} @endunless
                                        </span>
                                    </label>
                                @endforeach
                            </fieldset>
                            @error('variant_id')<p class="ksm-error" style="flex-basis: 100%;">{{ $message }}</p>@enderror
                        @endif
                        @error('quantita')<p class="ksm-error" style="flex-basis: 100%;">{{ $message }}</p>@enderror
                        <input class="ksm-input" style="width: 90px;" type="number" name="quantita" value="1" min="1" aria-label="{{ __('storefront.quantity') }}">
                        <button class="ksm-btn ksm-btn--primary" type="submit" @disabled(! $product->isInStock())>
                            {{ __('site.add_to_cart') }}
                        </button>
                    </form>
                </div>
            </div>

            @if ($product->description)
                <div class="ksm-card ksm-richtext" style="padding: 22px; margin-top: 28px;">
                    {{ \App\Support\RichText::render($product->description) }}
                </div>
            @endif

            @if ($related->isNotEmpty())
                <div class="ksm-section-head" style="margin-top: 40px;"><h2>Dalla stessa azienda</h2></div>
                <div class="ksm-shop__grid" data-cols="4">
                    @foreach ($related as $item)
                        <x-product-card :product="$item" :storefront="true" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection

