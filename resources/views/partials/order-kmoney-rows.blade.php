{{-- Sotto il totale di un ordine: quota KMoney e parte in euro, ognuna col suo stato. --}}
@if ($order->hasKmoney())
    <tr>
        <td class="ksm-muted">In KMoney, {{ $order->kmoneyPayment?->status === 'completed' ? 'pagati' : 'in attesa' }}</td>
        <td style="text-align: right;">{{ number_format((float) $order->kmoney_total, 2, ',', '.') }} KY</td>
    </tr>
    @if ($order->payment)
        <tr>
            <td class="ksm-muted">In euro, {{ $order->payment->status === 'completed' ? 'pagati' : 'in attesa' }}</td>
            <td style="text-align: right;">{{ \App\Support\Money::format($order->euro_total) }}</td>
        </tr>
    @endif
@endif
