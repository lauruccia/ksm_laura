<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function storeForCompany(Request $request, Company $company): RedirectResponse
    {
        // Il modulo sta sulla pagina dell'azienda: senza pagina non c'e'.
        abort_unless($company->hasPage(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $recent = Review::where('company_id', $company->id)
            ->where('ip', $request->ip())
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($recent) {
            return back()->with('error', __('Hai gia lasciato una recensione di recente.'));
        }

        Review::create($data + [
            'company_id' => $company->id,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        return back()->with('success', __('Recensione pubblicata.'));
    }

    public function storeForProduct(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $hasBought = $product->company->orders()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['paid', 'shipped', 'completed'])
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists();

        if (! $hasBought) {
            return back()->with('error', __('Puoi recensire solo i prodotti che hai acquistato.'));
        }

        ProductReview::updateOrCreate(
            ['product_id' => $product->id, 'user_id' => $request->user()->id],
            $data
        );

        return back()->with('success', __('Recensione salvata.'));
    }
}
