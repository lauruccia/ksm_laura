<?php

namespace App\Http\Controllers;

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
        ]);
    }

    public function add(Request $request, Product $product): RedirectResponse
    {
        // Il piano scaduto toglie lo shop: i prodotti restano, non si comprano.
        abort_unless(
            $product->status === 'active' && $product->company->allows(PlanCapabilities::SHOP),
            404
        );

        if (! $product->isInStock()) {
            return back()->with('error', __('Prodotto esaurito.'));
        }

        if ($this->cart->belongsToOtherCompany($product)) {
            return back()->with('error', __('Il carrello contiene prodotti di un altro venditore. Completa o svuota l\'ordine in corso.'));
        }

        $this->cart->add($product, max(1, $request->integer('quantita', 1)));

        return back()->with('success', __('Prodotto aggiunto al carrello.'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->cart->updateQuantity($product, $request->integer('quantita', 1));

        return back()->with('success', __('Carrello aggiornato.'));
    }

    public function remove(Product $product): RedirectResponse
    {
        $this->cart->remove($product);

        return back()->with('success', __('Prodotto rimosso.'));
    }

    public function clear(): RedirectResponse
    {
        $this->cart->clear();

        return redirect()->route('cart.index')->with('success', __('Carrello svuotato.'));
    }
}
