<?php

namespace App\Integrations\ManagementApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Integrations\ManagementApi\Support\Cursor;
use App\Integrations\ManagementApi\Support\Decimal;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(100, max(1, $request->integer('limit', 50)));
        $query = $this->query($request);

        if ($request->filled('cursor')) {
            try {
                $cursor = Cursor::decode((string) $request->query('cursor'));
            } catch (InvalidArgumentException) {
                return $this->invalidCursor($request);
            }

            $query->where(fn (Builder $query) => $query
                ->where('updated_at', '>', $cursor['updated_at'])
                ->orWhere(fn (Builder $query) => $query->where('updated_at', $cursor['updated_at'])->where('id', '>', $cursor['id'])));
        }

        $orders = $query->orderBy('updated_at')->orderBy('id')->limit($limit + 1)->get();
        $hasMore = $orders->count() > $limit;
        $orders = $orders->take($limit)->values();
        $last = $orders->last();

        return response()->json([
            'data' => $orders->map(fn (Order $order) => $this->serialize($order))->all(),
            'meta' => [
                'limit' => $limit,
                'has_more' => $hasMore,
                'next_cursor' => $last ? Cursor::encode($last->updated_at->utc()->toISOString(), $last->id) : null,
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $order = $this->query($request)->where('public_id', $id)->first();

        if (! $order) {
            return $this->notFound($request);
        }

        return response()->json(['data' => $this->serialize($order)]);
    }

    private function query(Request $request): Builder
    {
        return Order::query()
            ->with(['items.product', 'items.variant', 'payment', 'kmoneyPayment'])
            ->where('company_id', $request->attributes->get('management_company')->id);
    }

    private function serialize(Order $order): array
    {
        return [
            'id' => $order->public_id,
            'reference' => $order->reference,
            'commercial_status' => $order->status,
            'payment_status' => $this->paymentStatus($order),
            'currency' => $order->currency ?: config('ksm.currency', 'EUR'),
            'subtotal_cents' => Decimal::cents($order->subtotal),
            'shipping_cents' => Decimal::cents($order->shipping),
            'tax_cents' => Decimal::cents($order->tax),
            'total_cents' => Decimal::cents($order->total),
            'stock_effect' => $order->stock_deducted_at ? 'deducted' : 'none',
            'lines' => $order->items->map(fn (OrderItem $item) => [
                'id' => $item->public_id,
                'product_id' => $item->product?->public_id,
                'variant_id' => $item->variant?->public_id,
                'sku' => $item->variant?->variant_sku ?: $item->product?->sku,
                'name' => $item->product_name,
                'quantity' => (int) $item->quantity,
                'unit_price_cents' => Decimal::cents($item->product_price),
                'total_cents' => Decimal::cents($item->subtotal),
            ])->all(),
            'payment_allocations' => $order->requiredPayments()->map(fn (Payment $payment) => [
                'method' => $payment->method,
                'currency' => $payment->currency,
                'amount_cents' => Decimal::cents($payment->amount),
                'status' => $payment->status,
            ])->all(),
            'version' => (int) $order->integration_version,
            'created_at' => $order->created_at->utc()->toISOString(),
            'updated_at' => $order->updated_at->utc()->toISOString(),
        ];
    }

    private function paymentStatus(Order $order): string
    {
        $payments = $order->requiredPayments();

        if ($payments->isEmpty()) {
            return 'unpaid';
        }

        if ($order->isFullyPaid()) {
            return 'paid';
        }

        return $payments->contains(fn (Payment $payment) => $payment->status === 'completed') ? 'partial' : 'pending';
    }

    private function invalidCursor(Request $request): JsonResponse
    {
        return response()->json(['error' => [
            'code' => 'invalid_cursor',
            'message' => 'Il cursore non e valido.',
            'correlation_id' => $request->attributes->get('correlation_id'),
        ]], 422);
    }

    private function notFound(Request $request): JsonResponse
    {
        return response()->json(['error' => [
            'code' => 'not_found',
            'message' => 'Ordine non trovato per l azienda associata al token.',
            'correlation_id' => $request->attributes->get('correlation_id'),
        ]], 404);
    }
}
