<x-mail::message>
@if ($daysLeft <= 0)
# Il piano {{ $plan->name }} e scaduto

La scheda di **{{ $company->name }}** è stata tolta dalla directory il
{{ $endsAt?->format('d/m/Y') }}. I dati, i prodotti e gli ordini sono tutti
al loro posto: basta rinnovare per rimetterla online.
@else
# Il piano {{ $plan->name }} sta per scadere

Il piano di **{{ $company->name }}** scade il {{ $endsAt?->format('d/m/Y') }},
@if ($daysLeft === 1) domani. @else fra {{ $daysLeft }} giorni. @endif

Se non lo rinnovi entro quella data, la scheda esce dalla directory e lo
shop si ferma. Nessun dato viene cancellato.
@endif

<x-mail::button :url="$url">
{{ $daysLeft <= 0 ? 'Rinnova il piano' : 'Rinnova ora' }}
</x-mail::button>

Quota: {{ \App\Support\Money::format($plan->price) }}.
</x-mail::message>
