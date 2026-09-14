@props(['company'])

@php
    use App\Support\PlanCapabilities;

    // Cosa si vede della scheda lo decide il piano, non i file caricati.
    $showBanner = $company->banner && $company->allows(PlanCapabilities::BANNER);
    $showLogo = $company->logo && $company->allows(PlanCapabilities::LOGO);
    $featured = $company->allows(PlanCapabilities::FEATURED);
@endphp

{{-- Un biglietto con le proporzioni di una carta di credito. --}}
<article class="ksm-card ksm-bizcard @if ($showBanner) ksm-bizcard--banner @endif"
         @if ($showBanner) style="--ksm-bizcard-image: url('{{ asset('storage/'.$company->banner) }}')" @endif>
    <div class="ksm-bizcard__head">
        @if ($showLogo)
            <span class="ksm-bizcard__logo" style="background-image: url('{{ asset('storage/'.$company->logo) }}')"></span>
        @else
            <span class="ksm-bizcard__logo ksm-bizcard__logo--empty" aria-hidden="true">{{ mb_strtoupper(mb_substr($company->name, 0, 1)) }}</span>
        @endif

        <div class="ksm-bizcard__id">
            <h3 class="ksm-bizcard__name">
                <a href="{{ route('companies.show', $company->slug) }}">{{ $company->name }}</a>
            </h3>

            @if ($company->city)
                <p class="ksm-bizcard__city">
                    <x-icon name="pin" :size="14" />
                    <span>{{ $company->city }}</span>
                </p>
            @endif
        </div>

        @if ($featured)
            <x-rating :value="$company->reviews_avg_rating" />
        @endif
    </div>

    <ul class="ksm-meta ksm-bizcard__meta">
        @if ($company->category)
            <li>
                <x-icon name="tag" :size="15" />
                <span>{{ $company->category->name }}</span>
            </li>
        @endif
        @if ($company->email)
            <li class="ksm-bizcard__mail">
                <x-icon name="mail" :size="15" />
                <a href="mailto:{{ $company->email }}">{{ $company->email }}</a>
            </li>
        @endif
        @if ($company->phone)
            <li>
                <x-icon name="phone" :size="15" />
                <a href="tel:{{ $company->phone }}">{{ $company->phone }}</a>
            </li>
        @endif
        @if ($company->website)
            <li class="ksm-bizcard__web">
                <x-icon name="link" :size="15" />
                <a href="{{ $company->website }}" target="_blank" rel="noopener nofollow">{{ $company->website }}</a>
            </li>
        @endif
    </ul>

    <a class="ksm-bizcard__more" href="{{ route('companies.show', $company->slug) }}" tabindex="-1" aria-hidden="true">
        <x-icon name="arrow" :size="16" />
    </a>
</article>
