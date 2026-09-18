@props(['plan', 'highlight' => false])

<article class="ksm-card" style="padding: 24px; {{ $highlight ? 'outline: 2px solid var(--ksm-accent);' : '' }}">
    <h3>{{ $plan->name }}</h3>

    <p class="ksm-product__price" style="font-size: 1.8rem;">
        {{ $plan->isFree() ? 'Gratis' : \App\Support\Money::format($plan->price) }}
        @unless ($plan->isFree())
            <small class="ksm-muted" style="display: inline-block; font-size: .95rem; line-height: 1.2; white-space: nowrap;">{{ match (true) {
                $plan->isLifetime() => 'una tantum',
                $plan->duration_days == 365 => '/ anno',
                default => '/ '.$plan->duration_days.' giorni',
            } }}</small>
        @endunless
    </p>

    @if ($plan->description)
        <p class="ksm-muted">{{ $plan->description }}</p>
    @endif

    @if ($plan->features)
        <ul class="ksm-meta">
            @foreach ($plan->features as $feature)
                <li>{{ $feature }}</li>
            @endforeach
        </ul>
    @elseif ($plan->capabilityLabels())
        <ul class="ksm-meta">
            @foreach ($plan->capabilityLabels() as $label)
                <li>{{ $label }}</li>
            @endforeach
        </ul>
    @endif

    <div style="margin-top: 18px;">{{ $slot }}</div>
</article>
