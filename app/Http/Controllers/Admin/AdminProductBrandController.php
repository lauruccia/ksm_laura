<?php

namespace App\Http\Controllers\Admin;

use App\Models\ProductBrand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminProductBrandController extends AdminResourceController
{
    protected string $model = ProductBrand::class;

    protected string $title = 'Marche';

    protected string $routePrefix = 'admin.brands';

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Nome', 'type' => 'text'],
            'slug' => ['label' => 'Slug', 'type' => 'text'],
            'description' => ['label' => 'Descrizione', 'type' => 'textarea'],
        ];
    }

    protected function rules(Request $request, ?Model $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('product_brands', 'slug')->ignore($record)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function transform(array $data, Request $request, ?Model $record = null): array
    {
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        return $data;
    }
}
