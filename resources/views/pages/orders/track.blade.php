@extends('layouts.app')

@section('title', __('site.track_order'))

@section('content')
    <section class="ksm-section">
        <div class="ksm-container" style="max-width: 680px;">
            <div class="ksm-section-head"><h2>{{ __('site.track_order') }}</h2></div>

            <form class="ksm-card" style="padding: 22px;" method="POST" action="{{ route('orders.track.lookup') }}">
                @csrf
                <div class="ksm-field">
                    <label class="ksm-label" for="reference">Riferimento ordine</label>
                    <input class="ksm-input" id="reference" name="reference" placeholder="KSM-000123" required>
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="email">Email dell'ordine</label>
                    <input class="ksm-input" id="email" name="email" type="email" required>
                </div>
                <button class="ksm-btn ksm-btn--primary" type="submit">Cerca</button>
            </form>

            @if (($notFound ?? false))
                <p class="ksm-alert ksm-alert--error" style="margin-top: 20px;">Nessun ordine trovato.</p>
            @endif

            @if ($order)
                <div class="ksm-card" style="padding: 22px; margin-top: 20px;">
                    <h3>{{ $order->reference }}</h3>
                    <p><span class="ksm-badge">{{ $order->status }}</span></p>
                    <p>Totale {{ \App\Support\Money::format($order->total) }}</p>
                </div>
            @endif
        </div>
    </section>
@endsection
