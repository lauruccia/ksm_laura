<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function show(CmsPage $page): View
    {
        abort_unless($page->status === 'published' && $page->visibility === 'visible', 404);

        return view('pages.cms', ['page' => $page]);
    }
}
