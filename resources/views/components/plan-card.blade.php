@props(['plan', 'highlight' => false, 'featured' => false, 'compare' => null])

@php
    // Con `compare` (vedi Plan::featureUnion) la scheda elenca anche le voci che il piano non ha.
    $own = $plan->featureItems();
    $ownKeys = array_map([\App\Models\Plan::class, 'featureKey'], $own);
    $items = collect($compare ?: array_combine($ownKeys, $own))
        ->map(fn ($label, $key) => ['label' => $label, 'included' => in_array($key, $ownKeys, true)]);

    $period = match (true) {
        $plan->isFree() => null,
        $plan->isLifetime() => 'una tantum',
        $plan->duration_days == 365 => 'all\'anno',
        default => 'ogni '.$plan->duration_days.' giorni',
    };
@endphp

<article {{ $attributes->class([
    'ksm-plan',
    'ksm-plan--featured' => $featured,
    'ksm-plan--selected' => $highlight,
]) }}>
    @if ($featured)
        <span class="ksm-plan__badge"><x-icon name="star" :size="14" /> Il più completo</span>
    @endif

    <header class="ksm-plan__head">
        <h3 class="ksm-plan__name">{{ $plan->name }}</h3>
        @if ($plan->description)
            <p class="ksm-plan__desc">{{ $plan->description }}</p>
        @endif
    </header>

    <div class="ksm-plan__price">
        <strong>{{ $plan->isFree() ? 'Gratis' : \App\Support\Money::format($plan->price) }}</strong>
        @if ($period)
            <span>{{ $period }}</span>
        @endif
    </div>

    @if ($items->isNotEmpty())
        <p class="ksm-plan__label">Cosa comprende</p>
        <ul class="ksm-plan__features">
            @foreach ($items as $item)
                @if ($item['included'])
                    <li><x-icon name="check" :size="16" /> <span>{{ $item['label'] }}</span></li>
                @else
                    <li class="is-missing">
                        <x-icon name="close" :size="16" />
                        <span><span class="ksm-sr-only">Non incluso: </span>{{ $item['label'] }}</span>
                    </li>
                @endif
            @endforeach
        </ul>
    @endif

    <div class="ksm-plan__action">{{ $slot }}</div>
</article>
