<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    public function form(): View
    {
        return view('pages.orders.track', ['order' => null, 'notFound' => false]);
    }

    public function lookup(Request $request): View
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email'],
        ]);

        $id = (int) preg_replace('/\D/', '', $data['reference']);

        // Un ordine si traccia solo dal sito su cui e' stato fatto.
        $order = Order::with('items')
            ->forSite()
            ->whereKey($id)
            ->where('billing_email', $data['email'])
            ->first();

        return view('pages.orders.track', [
            'order' => $order,
            'notFound' => $order === null,
        ]);
    }
}
