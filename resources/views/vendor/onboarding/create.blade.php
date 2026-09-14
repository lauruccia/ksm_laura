@extends('layouts.app')

@section('title', 'Attiva la tua azienda')

@section('content')
    <section class="ksm-section">
        <div class="ksm-container" style="max-width: 720px;">
            <div class="ksm-section-head">
                <div>
                    <h2>{{ __('site.step_company_title') }}</h2>
                    <p>{{ __('site.step_company_text') }}</p>
                </div>
            </div>

            <form class="ksm-card" style="padding: 28px;" method="POST" action="{{ route('onboarding.store') }}">
                @csrf

                <div class="ksm-field">
                    <label class="ksm-label" for="name">Nome dell'azienda</label>
                    <input class="ksm-input" id="name" name="name" value="{{ old('name') }}" required>
                    @error('name')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="category_id">Categoria</label>
                    <select class="ksm-select" id="category_id" name="category_id">
                        <option value="">{{ __('site.search_all_categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="address">Indirizzo</label>
                    <input class="ksm-input" id="address" name="address" value="{{ old('address') }}">
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="city">Città</label>
                    <input class="ksm-input" id="city" name="city" value="{{ old('city') }}">
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="region">{{ __('site.search_region') }}</label>
                    <select class="ksm-select" id="region" name="region">
                        <option value="">Nessuna</option>
                        @foreach (config('ksm.regions') as $region)
                            <option value="{{ $region }}" @selected(old('region') === $region)>{{ $region }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="phone">Telefono</label>
                    <input class="ksm-input" id="phone" name="phone" value="{{ old('phone') }}">
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="email">Email pubblica</label>
                    <input class="ksm-input" id="email" name="email" type="email" value="{{ old('email') }}">
                    <small class="ksm-muted">Se la lasci vuota usiamo quella del tuo account.</small>
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="website">Sito</label>
                    <input class="ksm-input" id="website" name="website" type="url" value="{{ old('website') }}">
                    @error('website')<span class="ksm-error">{{ $message }}</span>@enderror
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="company_description">Descrizione</label>
                    <textarea class="ksm-textarea" id="company_description" name="company_description"
                              rows="4">{{ old('company_description') }}</textarea>
                </div>

                <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">
                    Continua e scegli il piano
                </button>
            </form>
        </div>
    </section>
@endsection
