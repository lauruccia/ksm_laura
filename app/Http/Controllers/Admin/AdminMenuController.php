<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use App\Models\CmsPage;
use App\Support\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Menu del sito principale, uno per posizione: barra in alto, testata e piede.
 *
 * Ogni posizione si salva da sola. Senza voci vale il menu predefinito,
 * cosi' il sito resta com'era finche' non si scrive qualcosa. I domini
 * della rete hanno i loro menu nella propria scheda.
 */
class AdminMenuController extends Controller
{
    public function edit(): View
    {
        $saved = (array) (AdminSetting::current()->menus ?? []);

        $menus = collect(Navigation::MENUS)->map(fn ($label, $location) => [
            'label' => $label,
            'custom' => ! empty($saved[$location]),
            'items' => ! empty($saved[$location]) ? $saved[$location] : Navigation::defaults($location),
        ]);

        return view('admin.menus.edit', [
            'menus' => $menus,
            'destinations' => $this->destinations(),
            'maxItems' => Navigation::MAX_ITEMS,
        ]);
    }

    public function update(Request $request, string $location): RedirectResponse
    {
        abort_unless(array_key_exists($location, Navigation::MENUS), 404);

        $data = $request->validate([
            'items' => ['nullable', 'array', 'max:'.Navigation::MAX_ITEMS],
            'items.*.label' => ['nullable', 'required_with:items.*.url', 'string', 'max:60'],
            // Indirizzi interni (/prodotti), ancore (#catalogo) o siti esterni: mai javascript: e simili.
            'items.*.url' => ['nullable', 'required_with:items.*.label', 'string', 'max:500', 'regex:/^(\/|#|https?:\/\/)/i'],
            'items.*.new_tab' => ['nullable', 'boolean'],
        ], [
            'items.*.label.required_with' => 'Ogni voce con un link ha bisogno di un\'etichetta.',
            'items.*.url.required_with' => 'Ogni voce ha bisogno di un link.',
            'items.*.url.regex' => 'Il link deve iniziare con /, # oppure https://.',
        ]);

        $items = $request->boolean('reset') ? [] : array_values(array_map(fn ($row) => [
            'label' => trim($row['label']),
            'url' => trim($row['url']),
            'new_tab' => (bool) ($row['new_tab'] ?? false),
        ], array_filter($data['items'] ?? [], fn ($row) => filled($row['label'] ?? null) && filled($row['url'] ?? null))));

        $settings = AdminSetting::current();
        $menus = (array) ($settings->menus ?? []);
        $menus[$location] = $items;
        $settings->update(['menus' => array_filter($menus)]);

        return redirect()->route('admin.menus.edit')
            ->withFragment('menu-'.$location)
            ->with('success', $items
                ? 'Menu salvato: '.Navigation::MENUS[$location].'.'
                : 'Tornate le voci predefinite: '.Navigation::MENUS[$location].'.');
    }

    /** Pagine del sito proposte nel campo del link, per non doverle scrivere a mano. */
    private function destinations(): array
    {
        $pages = [
            route('home', [], false) => __('site.nav_home'),
            route('companies.index', [], false) => __('site.nav_companies'),
            route('products.index', [], false) => __('site.nav_products'),
            route('products.index', ['offerta' => 1], false) => __('site.shop_on_sale'),
            route('plans.index', [], false) => __('site.nav_plans'),
            route('contact', [], false) => __('site.nav_contact'),
            route('register.vendor', [], false) => __('site.register_company'),
            route('orders.track', [], false) => __('site.track_order'),
        ];

        foreach (CmsPage::query()->published()->orderBy('title')->get(['title', 'slug']) as $page) {
            $pages[route('pages.show', $page->slug, false)] = 'Pagina: '.$page->title;
        }

        return $pages;
    }
}
