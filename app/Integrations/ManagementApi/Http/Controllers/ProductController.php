<?php

namespace App\Integrations\ManagementApi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Integrations\ManagementApi\Support\Cursor;
use App\Integrations\ManagementApi\Support\Decimal;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(100, max(1, $request->integer('limit', 50)));
        $query = Product::query()->with('variants')->where('company_id', $request->attributes->get('management_company')->id);

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

        $products = $query->orderBy('updated_at')->orderBy('id')->limit($limit + 1)->get();
        $hasMore = $products->count() > $limit;
        $products = $products->take($limit)->values();
        $last = $products->last();

        return response()->json([
            'data' => $products->map(fn (Product $product) => $this->serialize($product))->all(),
            'meta' => [
                'limit' => $limit,
                'has_more' => $hasMore,
                'next_cursor' => $last ? Cursor::encode($last->updated_at->utc()->toISOString(), $last->id) : null,
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $product = Product::query()->with('variants')
            ->where('company_id', $request->attributes->get('management_company')->id)
            ->where('public_id', $id)->first();

        if (! $product) {
            return $this->notFound($request);
        }

        return response()->json(['data' => $this->serialize($product)]);
    }

    private function serialize(Product $product): array
    {
        $price = Decimal::cents($product->price);
        $discount = $product->discount_price === null ? null : Decimal::cents($product->discount_price);
        $effective = $discount !== null && $discount > 0 && $discount < $price ? $discount : $price;

        return [
            'id' => $product->public_id,
            'name' => $product->name,
            'sku' => $product->sku,
            'type' => $product->product_type === 'variable' ? 'variable' : 'product',
            'status' => $product->status,
            'price_cents' => $effective,
            'currency' => config('ksm.currency', 'EUR'),
            'stock_managed' => $product->stock !== null,
            'available_quantity' => $product->stock,
            'variants' => $product->variants->map(fn (ProductVariant $variant) => $this->serializeVariant($variant))->all(),
            'version' => (int) $product->integration_version,
            'updated_at' => $product->updated_at->utc()->toISOString(),
        ];
    }

    private function serializeVariant(ProductVariant $variant): array
    {
        $attributes = json_decode((string) $variant->attributes, true);
        $managed = filled($variant->variant_stock);

        return [
            'id' => $variant->public_id,
            'sku' => $variant->variant_sku,
            'attributes' => is_array($attributes) ? $attributes : array_filter([$variant->variant_type => $variant->variant_value]),
            'price_cents' => $variant->variant_price === null ? null : Decimal::cents($variant->variant_price),
            'stock_managed' => $managed,
            'available_quantity' => $managed ? (int) $variant->variant_stock : null,
            'version' => (int) $variant->integration_version,
            'updated_at' => $variant->updated_at->utc()->toISOString(),
        ];
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
            'message' => 'Prodotto non trovato per l azienda associata al token.',
            'correlation_id' => $request->attributes->get('correlation_id'),
        ]], 404);
    }
}
