{{-- Una categoria dell'albero con le sue sottocategorie. Vuole $node, $routePrefix, $maxLevels, $itemsLabel, $itemLabel. --}}
@php
    $category = $node['record'];
    $childCount = count($node['children']);
    $canNest = $node['level'] < $maxLevels;
    $parentName = $node['parent'];
    $deleteMessage = 'Eliminare «'.$category->name.'»?'
        .($childCount ? ' Le sue '.$childCount.' sottocategorie salgono di un livello.' : '')
        .($node['count'] ? ' '.$node['count'].' '.($node['count'] === 1 ? $itemLabel : $itemsLabel).' '
            .($parentName ? 'passano a «'.$parentName.'».' : 'restano senza categoria.') : '');
@endphp

<li class="ksm-cattree__item" id="categoria-{{ $category->id }}" role="treeitem"
    data-name="{{ \Illuminate\Support\Str::lower($category->name.' '.$category->slug) }}">
    <div class="ksm-cattree__row">
        @if ($childCount)
            <button class="ksm-cattree__toggle" type="button" aria-expanded="true"
                    aria-label="Apri o chiudi {{ $category->name }}">
                <x-icon name="chevron-down" :size="16" />
            </button>
        @else
            <span class="ksm-cattree__toggle ksm-cattree__toggle--leaf" aria-hidden="true"></span>
        @endif

        @if ($category instanceof \App\Models\CompanyCategory && $node['level'] === 1)
            <span class="ksm-cattree__icon"><x-icon :name="\App\Support\CategoryIcon::for($category)" :size="17" /></span>
        @endif

        <span class="ksm-cattree__label">
            <a class="ksm-cattree__name" href="{{ route($routePrefix.'.edit', $category) }}">{{ $category->name }}</a>
            <span class="ksm-cattree__slug">{{ $category->slug }}</span>
        </span>

        <span class="ksm-cattree__meta">
            @if ($childCount)
                <span class="ksm-badge ksm-badge--muted">{{ $childCount }} {{ $childCount === 1 ? 'sottocategoria' : 'sottocategorie' }}</span>
            @endif
            <span class="ksm-badge @if (! $node['total']) ksm-badge--muted @endif"
                  title="{{ $node['count'] }} direttamente in questa categoria">
                {{ $node['total'] }} {{ $node['total'] === 1 ? $itemLabel : $itemsLabel }}
            </span>
        </span>

        <span class="ksm-cattree__actions">
            @if ($canNest)
                <details class="ksm-cattree__add">
                    <summary class="ksm-btn ksm-btn--ghost ksm-btn--sm">+ Sottocategoria</summary>
                    <form class="ksm-cattree__addform" method="POST" action="{{ route($routePrefix.'.store') }}">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $category->id }}">
                        <label class="ksm-label" for="sub-{{ $category->id }}">Nuova sottocategoria di «{{ $category->name }}»</label>
                        <div style="display: flex; gap: 8px;">
                            <input class="ksm-input" id="sub-{{ $category->id }}" name="name" required maxlength="255" placeholder="Nome">
                            <button class="ksm-btn ksm-btn--primary ksm-btn--sm" type="submit">Aggiungi</button>
                        </div>
                    </form>
                </details>
            @endif
            <a class="ksm-btn ksm-btn--ghost ksm-btn--sm" href="{{ route($routePrefix.'.edit', $category) }}">Modifica</a>
            <form method="POST" action="{{ route($routePrefix.'.destroy', $category) }}"
                  onsubmit="return confirm(this.dataset.message);" data-message="{{ $deleteMessage }}">
                @csrf @method('DELETE')
                <button class="ksm-btn ksm-btn--ghost ksm-btn--sm ksm-cattree__delete" type="submit">Elimina</button>
            </form>
        </span>
    </div>

    @if ($childCount)
        <ul class="ksm-cattree" role="group">
            @foreach ($node['children'] as $child)
                @include('admin.categories._node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
