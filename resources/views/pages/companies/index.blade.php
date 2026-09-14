@extends('layouts.app')

@section('title', __('site.nav_companies').' · '.$tenant->brandName())

@section('content')
    <section class="ksm-section">
        <div class="ksm-container">
            <div class="ksm-section-head">
                <div>
                    <h2>{{ __('site.nav_companies') }}</h2>
                    <p>{{ $companies->total() }} {{ __('site.nav_companies') }}</p>
                </div>
            </div>

            <form method="GET" class="ksm-card" style="padding: 16px; margin-bottom: 24px;" role="search">
                <div class="ksm-grid ksm-grid--4" style="align-items: end;">
                    <div class="ksm-field" style="margin: 0;">
                        <label class="ksm-label" for="cerca">{{ __('site.search_submit') }}</label>
                        <input class="ksm-input" id="cerca" name="cerca" type="search" value="{{ request('cerca') }}"
                               placeholder="{{ __('site.search_placeholder') }}">
                    </div>
                    <div class="ksm-field" style="margin: 0;">
                        <label class="ksm-label" for="regione">{{ __('site.search_region') }}</label>
                        <select class="ksm-select" id="regione" name="regione">
                            <option value="">{{ __('site.search_all_regions') }}</option>
                            @foreach (config('ksm.regions') as $region)
                                <option value="{{ $region }}" @selected(request('regione') === $region)>{{ $region }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ksm-field" style="margin: 0;">
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
                </div>
            </form>

            <x-ad-slot placement="store_list_above_stores"
                       :city="request('citta')" :category="request()->integer('categoria') ?: null" />

            @if ($companies->isEmpty())
                <p class="ksm-muted">{{ __('site.no_results') }}</p>
            @else
                <div class="ksm-grid ksm-grid--4">
                    @foreach ($companies as $company)
                        <x-company-card :company="$company" />
                    @endforeach
                </div>

                <div style="margin-top: 28px;">{{ $companies->links() }}</div>
            @endif
        </div>
    </section>
@endsection
