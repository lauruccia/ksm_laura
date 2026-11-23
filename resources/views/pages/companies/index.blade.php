@extends('layouts.app')

@section('title', __('site.nav_companies').' · '.$tenant->brandName())

@section('content')
    <section class="ksm-section">
        <div class="ksm-container ksm-directory__container">
            <div class="ksm-directory">
                {{-- Menu delle categorie: sugli schermi stretti lo sostituisce la tendina del modulo. --}}
                <aside class="ksm-dirnav" aria-labelledby="ksm-dirnav-title">
                    <h2 class="ksm-dirnav__title" id="ksm-dirnav-title">{{ __('site.directory_categories') }}</h2>
                    <nav>
                        <a class="ksm-dirnav__item ksm-dirnav__all @empty($filters['category']) is-current @endempty"
                           href="{{ route('companies.index', request()->only(['cerca', 'regione', 'citta'])) }}"
                           @empty($filters['category']) aria-current="page" @endempty>{{ __('site.search_all_categories') }}</a>
                        @include('partials.category-nav', ['nodes' => $categoryNav])
                    </nav>
                </aside>

                <div class="ksm-directory__main">
                    <form method="GET" class="ksm-card ksm-directory__search" role="search">
                        <div class="ksm-field ksm-directory__query">
                            <label class="ksm-label" for="cerca">{{ __('site.search_submit') }}</label>
                            <input class="ksm-input" id="cerca" name="cerca" type="search" value="{{ request('cerca') }}"
                                   placeholder="{{ __('site.search_placeholder') }}">
                        </div>
                        <div class="ksm-field">
                            <label class="ksm-label" for="regione">{{ __('site.search_region') }}</label>
                            <select class="ksm-select" id="regione" name="regione">
                                <option value="">{{ __('site.search_all_regions') }}</option>
                                @foreach (config('ksm.regions') as $region)
                                    <option value="{{ $region }}" @selected(request('regione') === $region)>{{ $region }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- Nascosta accanto al menu, ma inviata lo stesso: una nuova ricerca resta nella categoria. --}}
                        <div class="ksm-field ksm-directory__category">
                            <label class="ksm-label" for="categoria">{{ __('site.search_category') }}</label>
                            <select class="ksm-select" id="categoria" name="categoria">
                                <option value="">{{ __('site.search_all_categories') }}</option>
                                @foreach ($categories as $id => $label)
                                    <option value="{{ $id }}" @selected(request('categoria') == $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="ksm-btn ksm-btn--primary" type="submit">
                            <x-icon name="search" :size="18" />
                            {{ __('site.search_submit') }}
                        </button>
                    </form>

                    <x-ad-slot placement="store_list_above_stores"
                               :city="request('citta')" :category="request()->integer('categoria') ?: null" />

                    @if ($companies->isEmpty())
                        <p class="ksm-muted">{{ __('site.no_results') }}</p>
                    @else
                        <div class="ksm-grid ksm-directory__grid" data-directory-grid>
                            @include('pages.companies.partials.cards')
                        </div>

                        @if ($companies->hasPages())
                            {{-- Senza JavaScript, o se una richiesta fallisce, resta la paginazione. --}}
                            <div class="ksm-directory__more" data-directory-more>
                                <span class="ksm-directory__status" role="status">
                                    <span class="ksm-directory__spinner" aria-hidden="true"></span>
                                    {{ __('site.directory_loading') }}
                                </span>
                                <button class="ksm-btn ksm-btn--primary ksm-directory__retry" type="button" data-directory-retry>
                                    {{ __('site.directory_retry') }}
                                </button>
                                <div data-directory-pager>{{ $companies->links() }}</div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
