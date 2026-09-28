<x-mail::message>
@if ($order->status === 'shipped')
# Il tuo ordine è in viaggio

Ciao {{ $order->billing_name }}, l'ordine **{{ $order->reference }}** di
**{{ $company?->name }}** è stato spedito.

@if ($order->carrier || $order->tracking_number)
@if ($order->carrier)Corriere: **{{ $order->carrier }}**  
@endif
@if ($order->tracking_number)Numero di spedizione: **{{ $order->tracking_number }}**
@endif
@endif

@if ($order->tracking_url)
<x-mail::button :url="$order->tracking_url">
Segui la spedizione
</x-mail::button>
@endif
@else
# Il tuo ordine è stato annullato

Ciao {{ $order->billing_name }}, l'ordine **{{ $order->reference }}** di
**{{ $company?->name }}** è stato annullato.

Per un eventuale rimborso di quanto già pagato, o per qualsiasi domanda,
rispondi a questa email: arriva direttamente a {{ $company?->name }}.
@endif

<x-mail::table>
| Prodotto | Q.tà | Importo |
|:--|:-:|--:|
@foreach ($order->items as $item)
| {{ $item->product_name }} | {{ $item->quantity }} | {{ \App\Support\Money::format($item->subtotal) }} |
@endforeach
| **Totale** | | **{{ \App\Support\Money::format($order->total) }}** |
</x-mail::table>

<x-mail::button :url="$url" color="success">
Vedi l'ordine
</x-mail::button>

Grazie,<br>
{{ $site }}
</x-mail::message>
