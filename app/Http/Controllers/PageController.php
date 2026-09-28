<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    /**
     * Una pagina dall'indirizzo. Lo slug si cerca solo fra le pagine del
     * sito corrente: la "chi-siamo" di KSM non si apre su un dominio della
     * rete, e ogni dominio puo' avere la sua.
     *
     * Non con il binding della rotta: gira prima di ResolveTenant, quando
     * il sito non e' ancora noto.
     */
    public function bySlug(string $slug): View
    {
        return $this->show(CmsPage::query()->forSite()->where('slug', $slug)->firstOrFail());
    }

    public function show(CmsPage $page): View
    {
        abort_unless($page->status === 'published' && $page->visibility === 'visible', 404);

        return view('pages.cms', ['page' => $page]);
    }
}
