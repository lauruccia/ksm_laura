@props(['value' => 0])

@php($rounded = (int) round((float) $value))

<span class="ksm-rating" title="{{ number_format((float) $value, 1) }}">
    @for ($i = 1; $i <= 5; $i++)
        <span class="ksm-rating__star @if ($i <= $rounded) ksm-rating__star--on @endif">&#9733;</span>
    @endfor
    @if ((float) $value > 0)
        <span class="ksm-rating__value">{{ number_format((float) $value, 1) }}</span>
    @endif
</span>
