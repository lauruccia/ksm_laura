@props(['company'])

@php
    use App\Support\CategoryIcon;
    use App\Support\PlanCapabilities;

    // Due biglietti. Chi ha la pagina (Ecommerce, Vetrina) ha il formato
    // carta di credito con banner, e tutta la scheda porta alla pagina.
    // Biglietto e anagrafica non hanno pagina: niente link.
    $hasPage = $company->hasPage();

    // Cosa si vede della scheda lo decide il piano, non i file caricati.
    // Il banner lo concede la sua voce, anche a chi non ha la pagina.
    $showBanner = $company->banner && $company->allows(PlanCapabilities::BANNER);
    // Senza pagina e senza banner da mostrare il biglietto resta compatto.
    $hasCover = $hasPage || $showBanner;
    // Il tondo del logo c'e' solo se il piano lo concede: senza, nemmeno l'iniziale.
    $logoAllowed = $company->allows(PlanCapabilities::LOGO);
    $showLogo = $company->logo && $logoAllowed;
    $showRating = $company->allows(PlanCapabilities::REVIEWS) || $company->allows(PlanCapabilities::FEATURED);

    // Nei dati importati i campi vuoti a volte valgono "-": si trattano come assenti.
    $filled = fn ($value) => trim((string) $value, " \t-–—./") === '' ? null : trim((string) $value);

    $address = $filled($company->address);
    $email = $filled($company->email);
    $phone = $filled($company->phone);
    $website = $filled($company->website);

    $place = collect([$filled($company->city), $filled($company->region)])->filter()->unique()->implode(', ');
    // Il sito si legge meglio senza protocollo e barra finale; il link resta intero.
    $websiteUrl = $website && ! preg_match('#^https?://#i', $website) ? 'https://'.$website : $website;
    $websiteLabel = $website ? rtrim(preg_replace('#^https?://(www\.)?#i', '', $website), '/') : null;
@endphp

<article class="ksm-card ksm-bizcard @unless ($hasCover) ksm-bizcard--compact @endunless @unless ($hasPage) ksm-bizcard--static @endunless">
    @if ($hasCover)
        {{-- Immagini vere e non sfondi: il browser le scarica solo quando la scheda arriva a schermo. --}}
        <div class="ksm-bizcard__cover @unless ($showBanner) ksm-bizcard__cover--plain @endunless">
            @if ($showBanner)
                <img src="{{ asset('storage/'.\App\Support\Images\ImageStore::thumb($company->banner)) }}"
                     alt="" loading="lazy" decoding="async" width="800" height="450">
            @endif
        </div>
    @endif

    @if ($logoAllowed || $showRating)
    <div class="ksm-bizcard__top">
        @if ($showLogo)
            <span class="ksm-bizcard__logo">
                <img src="{{ asset('storage/'.\App\Support\Images\ImageStore::thumb($company->logo)) }}"
                     alt="" loading="lazy" decoding="async" width="56" height="56">
            </span>
        @elseif ($logoAllowed)
            <span class="ksm-bizcard__logo ksm-bizcard__logo--empty" aria-hidden="true">{{ mb_strtoupper(mb_substr($company->name, 0, 1)) }}</span>
        @endif

        @if ($showRating)
            <span class="ksm-bizcard__rating">
                <x-rating :value="$company->reviews_avg_rating" />
                <span class="ksm-bizcard__count">{{ (int) $company->reviews_count }}</span>
            </span>
        @endif
    </div>
    @endif

    <div class="ksm-bizcard__id">
        <h3 class="ksm-bizcard__name">
            @if ($hasPage)
                <a href="{{ route('companies.show', $company->slug) }}">{{ $company->name }}</a>
            @else
                {{ $company->name }}
            @endif
        </h3>

        @if ($place !== '')
            <p class="ksm-bizcard__place">{{ $place }}</p>
        @endif
    </div>

    {{-- La categoria non sta fra i contatti: si legge dall'icona del settore. --}}
    @if ($address || $email || $phone || $website)
    <ul class="ksm-meta ksm-bizcard__meta">
        @if ($address)
            <li class="ksm-bizcard__address">
                <x-icon name="pin" :size="16" />
                <span>{{ $address }}</span>
            </li>
        @endif
        @if ($email)
            <li class="ksm-bizcard__mail">
                <x-icon name="mail" :size="16" />
                <a href="mailto:{{ $email }}">{{ $email }}</a>
            </li>
        @endif
        @if ($phone)
            <li>
                <x-icon name="phone" :size="16" />
                <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">{{ $phone }}</a>
            </li>
        @endif
        @if ($website)
            <li class="ksm-bizcard__web">
                <x-icon name="globe" :size="16" />
                <a href="{{ $websiteUrl }}" target="_blank" rel="noopener nofollow">{{ $websiteLabel }}</a>
            </li>
        @endif
    </ul>
    @endif

    @if ($company->category)
        <span class="ksm-bizcard__sector" role="img" aria-label="{{ $company->category->name }}"
              title="{{ $company->category->name }}" data-sector-icon="{{ CategoryIcon::for($company->category) }}">
            <x-icon :name="CategoryIcon::for($company->category)" :size="17" />
        </span>
    @endif
</article>
