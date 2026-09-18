{{--
    Un livello del menu delle categorie. Chi ha sottocategorie si apre senza
    JavaScript e la prima voce dentro filtra per la categoria intera. Ricerca
    e luogo restano quelli scelti: cambia solo la categoria.
--}}
<ul class="ksm-dirnav__list @isset($whole) ksm-dirnav__list--nested @endisset">
    @isset($whole)
        <li>
            <a class="ksm-dirnav__item @if ($whole['current']) is-current @endif" href="{{ $whole['url'] }}"
               @if ($whole['current']) aria-current="page" @endif>
                {{ __('site.search_all_in_category', ['name' => $whole['name']]) }}
            </a>
        </li>
    @endisset

    @foreach ($tree->children($parent) as $id => $name)
        @php
            $url = route('companies.index', request()->only(['cerca', 'regione', 'citta']) + ['categoria' => $id]);
            $current = (int) ($filters['category'] ?? 0) === $id;
        @endphp

        <li>
            @if ($tree->children($id))
                <details class="ksm-dirnav__group" @if (in_array($id, $openCategories, true)) open @endif>
                    <summary class="ksm-dirnav__item">
                        <span>{{ $name }}</span>
                        <x-icon name="chevron-down" :size="16" />
                    </summary>

                    @include('pages.companies.partials.category-nav', [
                        'parent' => $id,
                        'whole' => ['name' => $name, 'url' => $url, 'current' => $current],
                    ])
                </details>
            @else
                <a class="ksm-dirnav__item @if ($current) is-current @endif" href="{{ $url }}"
                   @if ($current) aria-current="page" @endif>{{ $name }}</a>
            @endif
        </li>
    @endforeach
</ul>
