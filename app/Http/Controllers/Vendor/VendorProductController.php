<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Support\ProductForm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VendorProductController extends Controller
{
    public function __construct(private ProductForm $form) {}

    public function index(Request $request): View
    {
        $company = $request->user()->company;

        $products = $company->products()
            ->with('category')
            ->when($request->string('cerca')->toString(), fn ($q, $t) => $q->where('name', 'like', "%$t%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('vendor.products.index', [
            'products' => $products,
            'inDebt' => $this->form->inDebt($company),
            'kmoneySteps' => $this->form->kmoneySteps($company),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->formView($request, new Product());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->form->save($request, new Product(), $request->user()->company);

        return redirect()->route('vendor.products.index')->with('success', __('Prodotto creato.'));
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorizeProduct($request, $product);

        return $this->formView($request, $product->load('variants'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $this->form->save($request, $product, $request->user()->company);

        return back()->with('success', __('Prodotto aggiornato.'));
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $product->delete();

        return redirect()->route('vendor.products.index')->with('success', __('Prodotto eliminato.'));
    }

    public function toggleStatus(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $product->update(['status' => $product->status === 'active' ? 'inactive' : 'active']);

        return back()->with('success', __('Stato aggiornato.'));
    }

    private function formView(Request $request, Product $product): View
    {
        $company = $request->user()->company;

        return view('vendor.products.form', [
            'product' => $product,
            'inDebt' => $this->form->inDebt($company),
            'kmoneySteps' => $this->form->kmoneySteps($company),
            'categories' => ProductCategory::orderBy('name')->get(),
            'brands' => ProductBrand::orderBy('name')->get(),
        ]);
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->company_id === $request->user()->company->id, 403);
    }
}
