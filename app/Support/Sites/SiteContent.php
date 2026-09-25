<?php

namespace App\Support\Sites;

use App\Models\Domain;
use App\Support\Navigation;

/**
 * Contenuti del sito di un dominio: blocchi dello shop, menu, piede e SEO.
 *
 * Si leggono dal campo JSON `site` del dominio; cio' che manca prende il
 * valore del sito principale, cosi' un dominio appena creato funziona
 * gia' e il sito principale resta com'e'. Solo il piede e il menu, su un
 * dominio, non ripiegano sui contenuti di KSM: devono sembrare di un
 * altro sito.
 */
final class SiteContent
{
    /** Icone proposte per la fascia dei vantaggi. */
    public const BENEFIT_ICONS = [
        'truck' => 'Camion', 'award' => 'Coccarda', 'leaf' => 'Foglia', 'box' => 'Pacco',
        'shield' => 'Scudo', 'heart' => 'Cuore', 'star' => 'Stella', 'tag' => 'Etichetta',
        'sparkle' => 'Scintilla', 'building' => 'Azienda', 'grid' => 'Griglia', 'phone' => 'Telefono',
        'gift' => 'Regalo', 'check' => 'Spunta', 'users' => 'Persone', 'pin' => 'Luogo',
    ];

    public const FEATURED_SORTS = [
        'bestsellers' => 'Più venduti',
        'newest' => 'Novità',
        'offers' => 'In offerta',
    ];

    /** Voci di menu e link del piede per gruppo. */
    public const LINK_ROWS = 6;

    public function __construct(private readonly ?Domain $domain = null)
    {
    }

    public function isNetworkSite(): bool
    {
        return $this->domain !== null;
    }

    /** Un valore del campo `site`, o il predefinito se vuoto. */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = data_get($this->domain?->site, $key);

        return $value === null || $value === '' || $value === [] ? $default : $value;
    }

    /** Vero se la casella e' spuntata, o il predefinito se il dominio non l'ha mai salvata. */
    public function enabled(string $block, bool $default = true): bool
    {
        $value = data_get($this->domain?->site, "$block.enabled");

        return $value === null ? $default : (bool) $value;
    }

    public function hero(): array
    {
        $brand = $this->domain?->name ?? config('ksm.brand_name');

        return [
            'eyebrow' => $this->get('hero.eyebrow', $brand.' · Shop'),
            'title' => $this->get('hero.title', __('storefront.hero_title')),
            'highlight' => $this->get('hero.highlight', __('storefront.hero_accent')),
            'text' => $this->get('hero.text', __('storefront.hero_description')),
            // L'illustrazione predefinita porta il marchio KSM: su un dominio senza foto non si mostra.
            'image' => ($image = $this->get('hero.image'))
                ? asset('storage/'.$image)
                : ($this->isNetworkSite() ? null : asset('img/shop-hero.svg')),
            'has_photo' => (bool) $this->get('hero.image'),
            'primary_label' => $this->get('hero.primary_label', __('storefront.browse')),
            'primary_url' => $this->get('hero.primary_url', '#catalogo'),
            'secondary_label' => $this->get('hero.secondary_label', __('site.shop_on_sale')),
            'secondary_url' => $this->get('hero.secondary_url', route('products.index', ['offerta' => 1])),
            'badge_title' => $this->get('hero.badge_title'),
            'badge_text' => $this->get('hero.badge_text'),
            'badge_flag' => (bool) $this->get('hero.badge_flag', false),
            'script' => $this->get('hero.script'),
        ];
    }

    /** @return list<array{icon: string, title: string, text: ?string}> */
    public function benefits(): array
    {
        return $this->customBenefits() ?? array_map(fn ($icon, $key) => [
            'icon' => $icon,
            'title' => __("storefront.$key"),
            'text' => __("storefront.{$key}_detail"),
        ], ['building', 'grid', 'sparkle', 'tag'], ['vendors', 'selection', 'kmoney', 'offers']);
    }

    /**
     * Le voci scritte per il dominio, null se non ce ne sono: shop e home
     * hanno predefiniti diversi, ma le voci del dominio valgono per entrambi.
     *
     * @return list<array{icon: string, title: string, text: ?string}>|null
     */
    public function customBenefits(): ?array
    {
        $items = array_values(array_filter(
            (array) $this->get('benefits.items', []),
            fn ($item) => filled($item['title'] ?? null)
        ));

        return $items ? array_map(fn ($item) => [
            'icon' => array_key_exists($item['icon'] ?? '', self::BENEFIT_ICONS) ? $item['icon'] : 'check',
            'title' => $item['title'],
            'text' => $item['text'] ?? null,
        ], $items) : null;
    }

    public function categories(): array
    {
        return [
            'enabled' => $this->enabled('categories'),
            'title' => $this->get('categories.title', __('storefront.categories')),
            'link_label' => $this->get('categories.link_label', __('site.shop_all_products')),
            'ids' => array_map('intval', (array) $this->get('categories.ids', [])),
            'offers' => (bool) data_get($this->domain?->site, 'categories.offers', true),
        ];
    }

    /** La fila di prodotti in evidenza: sul sito principale non c'e'. */
    public function featured(): array
    {
        $sort = $this->get('featured.sort', 'bestsellers');

        return [
            'enabled' => $this->enabled('featured', $this->isNetworkSite()),
            'title' => $this->get('featured.title', __('storefront.featured_title')),
            'subtitle' => $this->get('featured.subtitle', __('storefront.featured_subtitle')),
            'sort' => array_key_exists($sort, self::FEATURED_SORTS) ? $sort : 'bestsellers',
            'count' => max(2, min(12, (int) $this->get('featured.count', 6))),
            'search' => (bool) data_get($this->domain?->site, 'featured.search', true),
        ];
    }

    public function catalog(): array
    {
        return [
            'enabled' => $this->enabled('catalog'),
            'title' => $this->get('catalog.title'),
        ];
    }

    /**
     * Voci di menu scritte per il dominio, null se non ce ne sono.
     *
     * @return list<array{label: string, url: string, active: bool}>|null
     */
    public function menu(string $side): ?array
    {
        $links = $this->links("menu.$side");

        return $links ? array_map(fn ($link) => $link + ['active' => Navigation::isCurrent($link['url'])], $links) : null;
    }

    public function footer(): array
    {
        return [
            'about' => $this->get('footer.about', $this->domain?->description),
            'links_title' => $this->get('footer.links_title', __('site.footer_quick_links')),
            'links' => $this->links('footer.links') ?: [
                ['label' => __('site.nav_home'), 'url' => route('home')],
                ['label' => __('site.nav_products'), 'url' => route('products.index')],
                ['label' => __('site.nav_companies'), 'url' => route('companies.index')],
                ['label' => __('site.nav_contact'), 'url' => route('contact')],
                ['label' => __('site.track_order'), 'url' => route('orders.track')],
            ],
            'info_title' => $this->get('footer.info_title', __('site.footer_pages')),
            'info' => $this->links('footer.info'),
            'legal' => $this->get('footer.legal'),
            'copyright' => $this->get('footer.copyright', '© '.date('Y').' '.($this->domain?->name ?? config('ksm.brand_name')).'. '.__('site.rights_reserved')),
        ];
    }

    public function seo(): array
    {
        return [
            'title' => $this->get('seo.title'),
            'description' => $this->get('seo.description', $this->domain?->description),
            'image' => ($image = $this->get('seo.image')) ? asset('storage/'.$image) : null,
        ];
    }

    /**
     * Colori del dominio come variabili CSS per tutta la pagina, non solo
     * per la testata: pulsanti, apertura e piede cambiano insieme.
     */
    public function cssVariables(): string
    {
        if (! $this->domain) {
            return '';
        }

        $vars = [];

        if ($dark = $this->hex($this->domain->header_background)) {
            $vars['--ksm-dark'] = $dark;
            $vars['--ksm-dark-deep'] = $this->mix($dark, '#000000', .3);
            $vars['--ksm-dark-soft'] = $this->mix($dark, '#ffffff', .12);
            $vars['--ksm-gradient-brand'] = 'linear-gradient(115deg, '.$vars['--ksm-dark-deep'].' 0%, '.$dark.' 55%, '.$vars['--ksm-dark-soft'].' 100%)';
        }

        if ($accent = $this->hex($this->domain->header_accent)) {
            $vars['--ksm-accent'] = $accent;
            $vars['--ksm-accent-dark'] = $this->mix($accent, '#000000', .18);
            $vars['--ksm-accent-light'] = $this->mix($accent, '#ffffff', .2);
            $vars['--ksm-accent-soft'] = $this->mix($accent, '#ffffff', .88);
            $vars['--brand-green-dark'] = $vars['--ksm-accent-dark'];
        }

        if ($ink = $this->hex($this->domain->header_color)) {
            $vars['--ksm-ink'] = $ink;
        }

        return implode(';', array_map(fn ($name, $value) => "$name:$value", array_keys($vars), $vars));
    }

    /** @return list<array{label: string, url: string}> */
    private function links(string $key): array
    {
        return array_values(array_map(
            fn ($link) => ['label' => (string) $link['label'], 'url' => (string) $link['url']],
            array_filter((array) $this->get($key, []), fn ($link) => filled($link['label'] ?? null) && filled($link['url'] ?? null))
        ));
    }

    private function hex(?string $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : null;
    }

    private function mix(string $color, string $with, float $amount): string
    {
        $a = sscanf($color, '#%02x%02x%02x');
        $b = sscanf($with, '#%02x%02x%02x');

        return vsprintf('#%02x%02x%02x', array_map(
            fn ($x, $y) => (int) round($x + ($y - $x) * $amount),
            $a,
            $b
        ));
    }
}
