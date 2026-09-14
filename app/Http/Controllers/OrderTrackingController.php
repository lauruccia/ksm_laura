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

        $order = Order::with('items')
            ->whereKey($id)
            ->where('billing_email', $data['email'])
            ->first();

        return view('pages.orders.track', [
            'order' => $order,
            'notFound' => $order === null,
        ]);
    }
}
