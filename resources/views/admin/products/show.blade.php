@extends('layouts.panel')

@section('title', $product->name.' · KSM')
@section('role', 'Amministrazione')
@section('nav')@include('admin.nav')@endsection

@section('content')
    <div class="ksm-panel__head">
        <h1>{{ $product->name }}</h1>
        <a class="ksm-btn ksm-btn--ghost" href="{{ route('admin.products.index') }}">Torna ai prodotti</a>
    </div>

    <div class="ksm-card" style="padding: 20px; max-width: 720px;">
        <ul class="ksm-meta">
            <li>Azienda: {{ $product->company?->name }}</li>
            <li>Prezzo: {{ \App\Support\Money::format($product->price) }}</li>
            <li>Giacenza: {{ $product->product_type === 'variable' ? 'Per variante' : ($product->stock ?? 'Non gestita') }}</li>
            <li>Tipo: {{ $product->product_type }}</li>
            <li>Stato: {{ $product->status }}</li>
        </ul>

        @if ($product->variants->isNotEmpty())
            <h2 style="font-size: 1rem; margin-top: 18px;">Varianti</h2>
            <table class="ksm-table">
                <tbody>
                @foreach ($product->variants as $variant)
                    <tr>
                        <td>{{ $variant->variant_type }}</td>
                        <td>{{ $variant->variant_value }}</td>
                        <td>{{ $variant->variant_price }}</td>
                        <td>Giacenza: {{ filled($variant->variant_stock) ? $variant->variant_stock : 'Non gestita' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
