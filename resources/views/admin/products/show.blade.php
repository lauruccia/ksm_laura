@extends('layouts.panel')

@section('title', $product->name.' · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <div>
            <h1>{{ $product->name }}</h1>
            <p class="ksm-panel__lead">
                Prodotto di <strong>{{ $product->company?->name }}</strong>
                <span class="ksm-badge @if ($product->status !== 'active') ksm-badge--muted @endif" style="margin-left: 6px;">
                    {{ $product->status === 'active' ? 'Attivo' : 'Non attivo' }}
                </span>
            </p>
        </div>
        <div class="ksm-rowactions">
            @can(\App\Support\Permissions::CATALOG_MANAGE)
                <a class="ksm-btn ksm-btn--primary" href="{{ route('admin.products.edit', $product) }}">Modifica</a>
            @endcan
            <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.products.index') }}">Torna ai prodotti</a>
        </div>
    </div>

    <div class="ksm-editor">
        <div class="ksm-editor__main">
            <section class="ksm-box">
                <div class="ksm-box__head"><h2>Dati</h2></div>
                <dl class="ksm-facts">
                    <div><dt>Prezzo</dt><dd>{{ \App\Support\Money::format($product->price) }}</dd></div>
                    <div><dt>Prezzo scontato</dt><dd>{{ $product->discount_price ? \App\Support\Money::format($product->discount_price) : '—' }}</dd></div>
                    <div><dt>Giacenza</dt><dd>{{ $product->product_type === 'variable' ? 'Per variante' : ($product->stock ?? 'Non gestita') }}</dd></div>
                    <div><dt>Tipo</dt><dd>{{ $product->product_type === 'variable' ? 'Con varianti' : 'Semplice' }}</dd></div>
                    <div><dt>Codice</dt><dd>{{ $product->sku ?: '—' }}</dd></div>
                    <div><dt>Quota KMoney</dt><dd>{{ $product->kmoney_percent }}%</dd></div>
                </dl>
            </section>

            @if ($product->variants->isNotEmpty())
                <section class="ksm-box">
                    <div class="ksm-box__head"><h2>Varianti</h2></div>
                    <div class="ksm-table-wrap">
                        <table class="ksm-table">
                            <thead><tr><th>Tipo</th><th>Valore</th><th>Prezzo</th><th>Giacenza</th><th>Codice</th></tr></thead>
                            <tbody>
                            @foreach ($product->variants as $variant)
                                <tr>
                                    <td>{{ $variant->variant_type }}</td>
                                    <td>{{ $variant->variant_value }}</td>
                                    <td>{{ filled($variant->variant_price) ? \App\Support\Money::format($variant->variant_price) : 'Come il prodotto' }}</td>
                                    <td>{{ filled($variant->variant_stock) ? $variant->variant_stock : 'Non gestita' }}</td>
                                    <td>{{ $variant->variant_sku ?: '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>

        <aside class="ksm-editor__side">
            <section class="ksm-box">
                <div class="ksm-box__head"><h2>Immagine principale</h2></div>
                @if ($product->featured_image)
                    <img class="ksm-media-current" src="{{ asset('storage/'.$product->featured_image) }}" alt="">
                @else
                    <div class="ksm-media-empty">Nessuna immagine</div>
                @endif
            </section>
        </aside>
    </div>
@endsection
