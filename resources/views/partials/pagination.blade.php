{{--
    Paginazione di tutto il sito, al posto di quella di Laravel pensata per
    Tailwind. Serve anche ai paginatori semplici, che non hanno $elements.
--}}
@if ($paginator->hasPages())
    <nav class="ksm-pagination" role="navigation" aria-label="{{ __('site.pagination_label') }}">
        @if ($paginator->onFirstPage())
            <span class="ksm-pagination__step is-disabled" aria-disabled="true">
                <x-icon name="arrow" :size="16" class="ksm-pagination__back" />
                <span>{{ __('site.pagination_prev') }}</span>
            </span>
        @else
            <a class="ksm-pagination__step" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                <x-icon name="arrow" :size="16" class="ksm-pagination__back" />
                <span>{{ __('site.pagination_prev') }}</span>
            </a>
        @endif

        @isset($elements)
            <ul class="ksm-pagination__pages">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li><span class="ksm-pagination__gap">&hellip;</span></li>
                    @elseif (is_array($element))
                        @foreach ($element as $page => $url)
                            <li>
                                @if ($page == $paginator->currentPage())
                                    <span class="ksm-pagination__page is-current" aria-current="page">{{ $page }}</span>
                                @else
                                    <a class="ksm-pagination__page" href="{{ $url }}">{{ $page }}</a>
                                @endif
                            </li>
                        @endforeach
                    @endif
                @endforeach
            </ul>
        @endisset

        @if ($paginator->hasMorePages())
            <a class="ksm-pagination__step" href="{{ $paginator->nextPageUrl() }}" rel="next">
                <span>{{ __('site.pagination_next') }}</span>
                <x-icon name="arrow" :size="16" />
            </a>
        @else
            <span class="ksm-pagination__step is-disabled" aria-disabled="true">
                <span>{{ __('site.pagination_next') }}</span>
                <x-icon name="arrow" :size="16" />
            </span>
        @endif
    </nav>
@endif
