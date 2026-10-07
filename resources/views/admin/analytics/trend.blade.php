@php
    /**
     * Andamento: barre per le pagine viste, linea e area per i visitatori.
     *
     * Disegnato a mano in SVG come il grafico del riepilogo: nessuna
     * libreria da caricare, e sta bene anche stampato. Una scala sola: le
     * pagine viste sono sempre almeno quanti i visitatori.
     */
    $width = 900;
    $height = 280;
    $padLeft = 46;
    $padRight = 12;
    $padTop = 16;
    $padBottom = 30;

    $count = max(1, count($series));
    $plotW = $width - $padLeft - $padRight;
    $floor = $height - $padBottom;
    $plotH = $floor - $padTop;
    $slot = $plotW / $count;
    $barWidth = max(2, min(26, $slot * 0.62));

    // Estremo alto "tondo" diviso in quattro righe di lettura intere: 7 diventa 8, 13 diventa 20, 90 diventa 100.
    $peak = max(1, max(array_column($series, 'views')));
    $quarter = max(1, $peak / 4);
    $magnitude = max(1, 10 ** (int) floor(log10($quarter)));
    $unit = collect([1, 2, 2.5, 5, 10])
        ->map(fn ($factor) => $factor * $magnitude)
        ->first(fn ($candidate) => $candidate >= $quarter && floor($candidate) == $candidate) ?? 10 * $magnitude;
    $top = (int) ($unit * 4);
    $levels = [0, 1, 2, 3, 4];
    $levelValue = fn ($level) => (int) round($top * $level / 4);

    $y = fn ($value) => $floor - ($value / $top) * $plotH;
    $x = fn ($i) => $padLeft + $slot * $i + $slot / 2;

    $line = collect($series)->map(fn ($point, $i) => round($x($i), 1).','.round($y($point['visitors']), 1))->implode(' ');
    $area = round($x(0), 1).','.$floor.' '.$line.' '.round($x($count - 1), 1).','.$floor;

    // Non piu' di una dozzina di etichette sotto l'asse, altrimenti si sovrappongono.
    $every = max(1, (int) ceil($count / 12));
@endphp

<div class="ksm-chart">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" role="img"
         aria-label="Pagine viste e visitatori, andamento nel periodo scelto">
        @foreach ($levels as $level)
            @php($ly = round($y($levelValue($level)), 1))
            <line class="ksm-chart__rule" x1="{{ $padLeft }}" y1="{{ $ly }}" x2="{{ $width - $padRight }}" y2="{{ $ly }}"></line>
            <text class="ksm-chart__label" x="{{ $padLeft - 8 }}" y="{{ $ly + 4 }}" text-anchor="end">{{ \App\Support\Analytics\Format::number($levelValue($level)) }}</text>
        @endforeach

        @foreach ($series as $i => $point)
            @php($h = max(($point['views'] / $top) * $plotH, $point['views'] > 0 ? 2 : 0))
            <rect class="ksm-chart__bar ksm-chart__bar--views"
                  x="{{ round($x($i) - $barWidth / 2, 1) }}" y="{{ round($floor - $h, 1) }}"
                  width="{{ round($barWidth, 1) }}" height="{{ round($h, 1) }}" rx="{{ $barWidth > 8 ? 3 : 1 }}">
                <title>{{ $point['title'] }}: {{ \App\Support\Analytics\Format::number($point['views']) }} pagine viste, {{ \App\Support\Analytics\Format::number($point['visitors']) }} visitatori, {{ \App\Support\Analytics\Format::number($point['sessions']) }} visite</title>
            </rect>
        @endforeach

        @if ($count > 1)
            <polygon class="ksm-chart__area" points="{{ $area }}"></polygon>
            <polyline class="ksm-chart__line" points="{{ $line }}"></polyline>
        @endif

        @if ($count <= 45)
            @foreach ($series as $i => $point)
                <circle class="ksm-chart__dot" cx="{{ round($x($i), 1) }}" cy="{{ round($y($point['visitors']), 1) }}" r="{{ $count <= 20 ? 3.4 : 2.4 }}">
                    <title>{{ $point['title'] }}: {{ \App\Support\Analytics\Format::number($point['visitors']) }} visitatori</title>
                </circle>
            @endforeach
        @endif

        @foreach ($series as $i => $point)
            @if ($i % $every === 0)
                <text class="ksm-chart__label" x="{{ round($x($i), 1) }}" y="{{ $height - 9 }}" text-anchor="middle">{{ $point['label'] }}</text>
            @endif
        @endforeach
    </svg>
</div>
