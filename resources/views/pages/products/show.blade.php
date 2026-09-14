@extends('layouts.app')

@section('title', $product->name)

@section('content')
    <section class="ksm-section">
        <div class="ksm-container">
            <x-ad-slot placement="product_details_above_product_detail"
                       :city="$product->company->city" :category="$product->company->category_id" />

            <div class="ksm-grid ksm-grid--2">
                <div class="ksm-card">
                    @if ($product->featured_image)
                        <img src="{{ asset('storage/'.$product->featured_image) }}" alt="{{ $product->name }}">
                    @endif
                </div>

                <div>
                    <h1 style="font-size: 1.9rem;">{{ $product->name }}</h1>
                    <p class="ksm-muted">
                        <a href="{{ route('companies.show', $product->company->slug) }}">{{ $product->company->name }}</a>
                    </p>

                    <p class="ksm-product__price" style="font-size: 1.6rem;">
                        {{ \App\Support\Money::format($product->final_price) }}
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

                    <form method="POST" action="{{ route('cart.add', $product->slug) }}" style="display: flex; gap: 10px;">
                        @csrf
                        <input class="ksm-input" style="width: 90px;" type="number" name="quantita" value="1" min="1">
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
                <div class="ksm-grid ksm-grid--2">
                    @foreach ($related as $item)
                        <x-product-card :product="$item" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
