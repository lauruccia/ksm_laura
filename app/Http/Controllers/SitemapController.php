<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;
use App\Models\Company;
use App\Models\Product;
use App\Support\PlanCapabilities;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Mappa del sito e istruzioni per i motori di ricerca.
 *
 * Con i dati veri le aziende sono quasi centomila, oltre il limite di
 * 50.000 indirizzi per file fissato dal protocollo: la mappa e' quindi un
 * indice che rimanda a file da PER_FILE indirizzi ciascuno. Ogni file
 * resta in cache qualche ora, perche' i motori la rileggono spesso.
 *
 * Elenca solo cio' che un visitatore puo' davvero aprire: aziende attive
 * in directory, prodotti in vendita, pagine pubblicate.
 */
class SitemapController extends Controller
{
    /**
     * Indirizzi per file. Il protocollo ne ammette 50.000; 5.000 tengono
     * ogni file sotto il megabyte, piu' leggero da servire e da rileggere.
     */
    public const PER_FILE = 5000;

    private const TTL_HOURS = 6;

    public function index(Request $request): Response
    {
        $xml = Cache::remember($this->key($request, 'indice'), now()->addHours(self::TTL_HOURS), function () {
            $files = [route('sitemap.section', ['section' => 'pagine', 'page' => 1])];

            foreach (['aziende' => $this->companies(), 'prodotti' => $this->products()] as $section => $query) {
                $pages = (int) ceil($query->count() / self::PER_FILE);

                for ($page = 1; $page <= $pages; $page++) {
                    $files[] = route('sitemap.section', ['section' => $section, 'page' => $page]);
                }
            }

            return view('sitemap-index', ['files' => $files])->render();
        });

        return $this->xml($xml);
    }

    public function section(Request $request, string $section, int $page): Response
    {
        $xml = Cache::remember($this->key($request, "$section-$page"), now()->addHours(self::TTL_HOURS), function () use ($section, $page) {
            $urls = match ($section) {
                'pagine' => $page === 1 ? $this->pages() : [],
                'aziende' => $this->slice($this->companies(), $page, 'companies.show', '0.8'),
                'prodotti' => $this->slice($this->products(), $page, 'products.show', '0.6'),
                default => [],
            };

            return $urls ? view('sitemap', ['urls' => $urls])->render() : null;
        });

        // Un file oltre l'ultimo non esiste: meglio un 404 che un file vuoto.
        abort_if($xml === null, 404);

        return $this->xml($xml);
    }

    /** Generato qui e non come file statico: ogni dominio indica la propria mappa. */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /amministrazione',
            'Disallow: /area-azienda',
            'Disallow: /account',
            'Disallow: /abbonamento',
            'Disallow: /attivazione',
            'Disallow: /carrello',
            'Disallow: /pagamento',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** Pagine fisse del sito e pagine CMS pubblicate. */
    private function pages(): array
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0'],
            ['loc' => route('companies.index'), 'priority' => '0.9'],
            ['loc' => route('products.index'), 'priority' => '0.9'],
            ['loc' => route('contact'), 'priority' => '0.5'],
        ];

        // I piani sono di KSM: un dominio della rete non li mette in mappa.
        if (! app(TenantContext::class)->isNetworkSite()) {
            array_splice($urls, 3, 0, [['loc' => route('plans.index'), 'priority' => '0.7']]);
        }

        $cms = CmsPage::query()
            ->published()
            ->where('visibility', 'visible')
            ->where('include_in_sitemap', true)
            ->orderBy('id')
            ->get(['slug', 'updated_at']);

        foreach ($cms as $page) {
            $urls[] = [
                'loc' => route('pages.show', $page->slug),
                'lastmod' => $page->updated_at?->toAtomString(),
                'priority' => '0.5',
            ];
        }

        return $urls;
    }

    /** Un file della mappa: righe in ordine di id, sempre le stesse per pagina. */
    private function slice(Builder $query, int $page, string $route, string $priority): array
    {
        $table = $query->getModel()->getTable();

        return $query
            ->orderBy("$table.id")
            ->forPage($page, self::PER_FILE)
            ->get(["$table.slug", "$table.updated_at"])
            ->map(fn ($row) => [
                'loc' => route($route, $row->slug),
                'lastmod' => $row->updated_at?->toAtomString(),
                'priority' => $priority,
            ])
            ->all();
    }

    private function companies(): Builder
    {
        // Solo chi ha una pagina: biglietto e anagrafica stanno nella directory.
        return app(TenantContext::class)->scope()->companies(Company::query()
            ->active()
            ->inDirectory()
            ->withPage());
    }

    private function products(): Builder
    {
        // Come nello shop: sui domini solo i prodotti del dominio.
        return app(TenantContext::class)->scope()->products(Product::query()
            ->active()
            ->whereHas('company', fn ($q) => $q->active()->selling()));
    }

    private function key(Request $request, string $suffix): string
    {
        return 'sitemap:'.self::PER_FILE.':'.$request->getHost().':'.$suffix;
    }

    private function xml(string $body): Response
    {
        return response($body)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
