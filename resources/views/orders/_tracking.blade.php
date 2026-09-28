{{-- Spedizione di un ordine per il cliente: corriere, numero e link, se ci sono. --}}
@if ($order->carrier || $order->tracking_number || $order->tracking_url)
    <div class="ksm-ordertracking">
        <p style="margin: 0;">
            <strong>Spedizione</strong>
            @if ($order->shipped_at) del {{ $order->shipped_at->translatedFormat('j F Y') }} @endif
        </p>
        <p style="margin: 4px 0 0;">
            @if ($order->carrier){{ $order->carrier }}@endif
            @if ($order->carrier && $order->tracking_number) · @endif
            @if ($order->tracking_number)n. {{ $order->tracking_number }}@endif
        </p>
        @if ($order->tracking_url)
            <p style="margin: 8px 0 0;"><a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ $order->tracking_url }}" target="_blank" rel="noopener noreferrer">Segui la spedizione</a></p>
        @endif
    </div>
@endif
