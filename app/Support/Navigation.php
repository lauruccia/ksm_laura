<?php

namespace App\Support;

use App\Models\AdminSetting;
use App\Models\CmsPage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Navigation
{
    /**
     * Posizioni dei menu del sito principale, in Amministrazione, Menu.
     * Una posizione senza voci mostra quelle predefinite; le barre in alto,
     * che prima non c'erano, restano vuote.
     */
    public const MENUS = [
        'top_left' => 'Barra in alto · a sinistra',
        'top_right' => 'Barra in alto · a destra',
        'header_left' => 'Menu principale · a sinistra del logo',
        'header_right' => 'Menu · a destra del logo',
        'footer_pages' => 'Piede · colonna Pagine',
        'footer_links' => 'Piede · colonna Link rapidi',
    ];

    /** Voci al massimo per posizione. */
    public const MAX_ITEMS = 12;

    /** Pagine CMS da mostrare in una data posizione: header o footer. */
    public static function pages(string $location): Collection
    {
        return Cache::remember(
            "cms.pages.$location",
            now()->addMinutes(10),
            fn () => CmsPage::query()
                ->published()
                ->inLocation($location)
                ->orderBy('sort_order')
                ->get(['id', 'title', 'slug'])
        );
    }

    public static function flush(): void
    {
        foreach (['header', 'footer'] as $location) {
            Cache::forget("cms.pages.$location");
        }
    }

    /**
     * Le voci scritte in Amministrazione per una posizione, null se non ce ne sono.
     *
     * @return list<array{label: string, url: string, new_tab: bool, active: bool}>|null
     */
    public static function custom(string $location): ?array
    {
        $items = self::saved()[$location] ?? [];

        return $items ? self::withState($items) : null;
    }

    /**
     * Le voci che valgono senza un menu scritto: quelle di sempre del sito.
     *
     * @return list<array{label: string, url: string, new_tab: bool, active: bool}>
     */
    public static function defaults(string $location): array
    {
        $page = fn ($page) => ['label' => $page->title, 'url' => route('pages.show', $page->slug, false)];

        $items = match ($location) {
            'header_left' => [
                ['label' => __('site.nav_home'), 'url' => route('home', [], false)],
                ['label' => __('site.nav_companies'), 'url' => route('companies.index', [], false)],
                ['label' => __('site.nav_products'), 'url' => route('products.index', [], false)],
                ['label' => __('site.nav_plans'), 'url' => route('plans.index', [], false)],
            ],
            'header_right' => [
                ['label' => __('site.nav_contact'), 'url' => route('contact', [], false)],
                ...self::pages('header')->map($page)->all(),
            ],
            'footer_pages' => [
                ['label' => __('site.nav_plans'), 'url' => route('plans.index', [], false)],
                ...self::pages('footer')->map($page)->all(),
                ['label' => __('site.track_order'), 'url' => route('orders.track', [], false)],
            ],
            'footer_links' => [
                ['label' => __('site.nav_home'), 'url' => route('home', [], false)],
                ['label' => __('site.nav_contact'), 'url' => route('contact', [], false)],
                ['label' => __('site.nav_companies'), 'url' => route('companies.index', [], false)],
                ['label' => __('site.nav_products'), 'url' => route('products.index', [], false)],
            ],
            default => [],
        };

        return self::withState($items);
    }

    /** Le voci scritte, altrimenti quelle predefinite. */
    public static function items(string $location): array
    {
        return self::custom($location) ?? self::defaults($location);
    }

    /**
     * Vero se il link porta alla pagina aperta.
     *
     * Le ancore portano a un punto della pagina, non a una pagina: non si
     * accendono. Con parametri, come /prodotti?offerta=1, devono esserci anche quelli.
     */
    public static function isCurrent(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        if ($path === null || $path === false || str_contains($url, '#')) {
            return false;
        }

        // Un link assoluto a un altro sito non e' mai la pagina aperta.
        $host = parse_url($url, PHP_URL_HOST);
        if ($host && strcasecmp($host, request()->getHost()) !== 0) {
            return false;
        }

        $current = '/'.ltrim(request()->path(), '/');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        foreach ($query as $name => $value) {
            if ((string) request()->query($name) !== (string) $value) {
                return false;
            }
        }

        return $path === '/' ? $current === '/' : str_starts_with($current, rtrim($path, '/'));
    }

    /** @return array<string, list<array{label: string, url: string, new_tab?: bool}>> */
    private static function saved(): array
    {
        // Una sola lettura per richiesta, anche se la testata chiede piu' posizioni.
        $attributes = request()->attributes;

        if (! $attributes->has('ksm.menus')) {
            $attributes->set('ksm.menus', (array) (AdminSetting::current()->menus ?? []));
        }

        return $attributes->get('ksm.menus');
    }

    private static function withState(array $items): array
    {
        return array_values(array_map(fn ($item) => [
            'label' => (string) $item['label'],
            'url' => (string) $item['url'],
            'new_tab' => (bool) ($item['new_tab'] ?? false),
            'active' => self::isCurrent((string) $item['url']),
        ], $items));
    }
}
