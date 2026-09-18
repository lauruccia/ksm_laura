<?php

namespace App\Http\Controllers\Admin;

use App\Models\ProductCategory;
use App\Support\Images\ImageStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AdminProductCategoryController extends AdminCategoryController
{
    protected string $model = ProductCategory::class;

    protected string $title = 'Categorie prodotti';

    protected string $routePrefix = 'admin.product_categories';

    /** La barra laterale del catalogo mostra solo categorie e sottocategorie. */
    protected int $maxLevels = 2;

    protected function fields(): array
    {
        return parent::fields() + [
            'image' => [
                'label' => 'Immagine',
                'type' => 'file',
                'hint' => 'Foto del riquadro della categoria nello shop dei domini. Vuoto: resta quella attuale, o l\'icona.',
            ],
        ];
    }

    protected function rules(Request $request, ?Model $record = null): array
    {
        return parent::rules($request, $record) + [
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
        ];
    }

    protected function transform(array $data, Request $request, ?Model $record = null): array
    {
        $data = parent::transform($data, $request, $record);
        unset($data['image']);

        if ($request->hasFile('image')) {
            $data['image'] = app(ImageStore::class)->store($request->file('image'), 'categories', 'product', 'image');
            app(ImageStore::class)->delete($record?->image);
        }

        return $data;
    }
}
