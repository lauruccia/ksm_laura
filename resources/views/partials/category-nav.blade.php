{{--
    Un livello del menu delle categorie, uguale per aziende e prodotti.
    Il nome porta alla pagina filtrata per la categoria; la freccia apre le
    sottocategorie, senza JavaScript. Ogni voce: id, name, url, current,
    open, children.
--}}
<ul class="ksm-dirnav__list @if ($nested ?? false) ksm-dirnav__list--nested @endif">
    @foreach ($nodes as $node)
        <li>
            @if ($node['children'])
                <details class="ksm-dirnav__group" @if ($node['open']) open @endif>
                    <summary class="ksm-dirnav__item ksm-dirnav__row @if ($node['current']) is-current @endif">
                        <a class="ksm-dirnav__link" href="{{ $node['url'] }}"
                           @if ($node['current']) aria-current="page" @endif>{{ $node['name'] }}</a>
                        <span class="ksm-dirnav__toggle" title="{{ __('site.directory_subcategories') }}">
                            <x-icon name="chevron-down" :size="16" />
                        </span>
                    </summary>

                    @include('partials.category-nav', ['nodes' => $node['children'], 'nested' => true])
                </details>
            @else
                <a class="ksm-dirnav__item @if ($node['current']) is-current @endif" href="{{ $node['url'] }}"
                   @if ($node['current']) aria-current="page" @endif>{{ $node['name'] }}</a>
            @endif
        </li>
    @endforeach
</ul>
