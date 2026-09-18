<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Product;
use App\Support\Cart;
use App\Support\PlanCapabilities;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly Cart $cart)
    {
    }

    public function index(): View
    {
        return view('pages.cart.index', [
            'items' => $this->cart->items(),
            'subtotal' => $this->cart->subtotal(),
            'company' => $this->cart->company(),
            'carts' => $this->cart->summary(),
        ]);
    }

    public function add(Request $request, Product $product): RedirectResponse
    {
        // Il piano scaduto toglie lo shop: i prodotti restano, non si comprano.
        abort_unless(
            $product->status === 'active' && $product->company->allows(PlanCapabilities::SHOP)
                // Sui domini della rete si compra solo cio' che il dominio mostra.
                && app(\App\Support\TenantContext::class)->scope()->allowsProduct($product),
            404
        );

        if (! $product->isInStock()) {
            return back()->with('error', __('Prodotto esaurito.'));
        }

        $request->validate(['quantita' => ['sometimes', 'integer', 'min:1', 'max:100000'], 'variant_id' => ['nullable', 'integer']]);
        $variant = $request->filled('variant_id') ? $product->variants()->findOrFail($request->integer('variant_id')) : null;

        // Un ordine e' di un venditore solo: il carrello dell'altro non si
        // svuota, resta in attesa e si riapre dalla pagina del carrello.
        $parked = $this->cart->belongsToOtherCompany($product) ? $this->cart->company() : null;

        $this->cart->add($product, $request->integer('quantita', 1), $variant);

        return back()->with('success', $parked
            ? __('Prodotto aggiunto: stai ordinando da :nuovo. Il carrello di :sospeso resta in attesa.', [
                'nuovo' => $product->company->name,
                'sospeso' => $parked->name,
            ])
            : __('Prodotto aggiunto al carrello.'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $request->validate(['quantita' => ['required', 'integer', 'min:0', 'max:100000'], 'variant_id' => ['nullable', 'integer']]);
        $variant = $request->filled('variant_id') ? $product->variants()->findOrFail($request->integer('variant_id')) : null;
        $this->cart->updateQuantity($product, $request->integer('quantita', 1), $variant);

        return back()->with('success', __('Carrello aggiornato.'));
    }

    public function remove(Request $request, Product $product): RedirectResponse
    {
        $request->validate(['variant_id' => ['nullable', 'integer']]);
        $this->cart->remove($product, $request->filled('variant_id') ? $request->integer('variant_id') : null);

        return back()->with('success', __('Prodotto rimosso.'));
    }

    public function clear(): RedirectResponse
    {
        $this->cart->clear();

        return redirect()->route('cart.index')->with('success', __('Carrello svuotato.'));
    }

    /** Riapre il carrello di un venditore e mette in attesa quello in corso. */
    public function open(Company $company): RedirectResponse
    {
        $this->cart->switchTo($company->id);

        return redirect()->route('cart.index')
            ->with('success', __('Stai ordinando da :azienda.', ['azienda' => $company->name]));
    }

    public function discard(Company $company): RedirectResponse
    {
        $this->cart->discard($company->id);

        return redirect()->route('cart.index')
            ->with('success', __('Carrello di :azienda eliminato.', ['azienda' => $company->name]));
    }
}
