<?php

namespace App\Http\Controllers;

use App\Models\DomainName;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** La pagina pubblica con i nomi a dominio puntati a KSM. */
class DomainNameController extends Controller
{
    public const PER_PAGE = 60;

    public function index(Request $request): View
    {
        $sort = array_key_exists($request->query('ordina'), DomainName::SORTS) ? $request->query('ordina') : 'nome';
        $extension = $this->text($request, 'estensione');
        $theme = $this->text($request, 'tema');
        $letter = strtolower($this->text($request, 'iniziale'));
        $letter = preg_match('/^[a-z0-9]$/', $letter) ? ($letter >= '0' && $letter <= '9' ? '0' : $letter) : '';
        $term = $this->text($request, 'cerca');

        $domains = DomainName::query()
            ->active()
            ->when($term !== '', fn ($q) => $q->matching($term))
            ->when($extension !== '', fn ($q) => $q->where('extension', $extension))
            ->when($theme !== '', fn ($q) => $q->where('category', $theme))
            ->when($letter !== '', fn ($q) => $q->startingWith($letter))
            ->sortedBy($sort)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('pages.domain-names.index', [
            'domains' => $domains,
            'total' => DomainName::active()->count(),
            'extensions' => DomainName::active()->selectRaw('extension, COUNT(*) as total')
                ->groupBy('extension')->orderBy('extension')->pluck('total', 'extension'),
            'themes' => DomainName::active()->whereNotNull('category')->selectRaw('category, COUNT(*) as total')
                ->groupBy('category')->orderBy('category')->pluck('total', 'category'),
            'filters' => compact('term', 'extension', 'theme', 'letter', 'sort'),
        ]);
    }

    private function text(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? trim($value) : '';
    }
}
