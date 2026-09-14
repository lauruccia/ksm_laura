@php
    /**
     * Grafico a barre con la linea degli ordini sopra.
     *
     * Disegnato a mano in SVG: nessuna libreria da caricare, e resta
     * leggibile anche se la pagina viene stampata.
     */
    $width = 720;
    $height = 240;
    $padLeft = 8;
    $padBottom = 28;
    $padTop = 16;

    $count = max(1, count($chart['labels']));
    $slot = ($width - $padLeft * 2) / $count;
    $barWidth = min(34, $slot * 0.52);

    $maxRevenue = max(1, max($chart['revenue']));
    $maxOrders = max(1, max($chart['orders']));
    $floor = $height - $padBottom;

    $barHeight = fn ($value) => ($value / $maxRevenue) * ($floor - $padTop);
    $linePoint = fn ($value) => $floor - ($value / $maxOrders) * ($floor - $padTop);

    $points = collect($chart['orders'])
        ->map(fn ($value, $i) => round($padLeft + $slot * $i + $slot / 2, 1).','.round($linePoint($value), 1))
        ->implode(' ');
@endphp

<div class="ksm-chart">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" role="img"
         aria-label="Incassato e numero di ordini per mese negli ultimi dodici mesi">
        {{-- Righe di lettura, non un reticolo: bastano tre livelli. --}}
        @foreach ([0, 0.5, 1] as $step)
            @php($y = $floor - $step * ($floor - $padTop))
            <line class="ksm-chart__rule" x1="0" y1="{{ $y }}" x2="{{ $width }}" y2="{{ $y }}"></line>
        @endforeach

        @foreach ($chart['revenue'] as $i => $value)
            @php($h = $barHeight($value))
            <rect class="ksm-chart__bar"
                  x="{{ round($padLeft + $slot * $i + ($slot - $barWidth) / 2, 1) }}"
                  y="{{ round($floor - $h, 1) }}"
                  width="{{ round($barWidth, 1) }}"
                  height="{{ round(max($h, 1), 1) }}" rx="4">
                <title>{{ $chart['labels'][$i] }}: {{ \App\Support\Money::format($value) }}</title>
            </rect>
        @endforeach

        <polyline class="ksm-chart__line" points="{{ $points }}"></polyline>

        @foreach ($chart['orders'] as $i => $value)
            <circle class="ksm-chart__dot"
                    cx="{{ round($padLeft + $slot * $i + $slot / 2, 1) }}"
                    cy="{{ round($linePoint($value), 1) }}" r="3.2">
                <title>{{ $chart['labels'][$i] }}: {{ $value }} ordini</title>
            </circle>
        @endforeach

        @foreach ($chart['labels'] as $i => $label)
            <text class="ksm-chart__label" x="{{ round($padLeft + $slot * $i + $slot / 2, 1) }}"
                  y="{{ $height - 8 }}" text-anchor="middle">{{ $label }}</text>
        @endforeach
    </svg>

    <p class="ksm-chart__scale">
        Massimo del periodo: {{ \App\Support\Money::format($maxRevenue) }} incassati,
        {{ $maxOrders }} ordini in un mese.
    </p>
</div>
