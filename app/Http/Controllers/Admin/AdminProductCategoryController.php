<?php

namespace App\Http\Controllers\Admin;

use App\Models\ProductCategory;

class AdminProductCategoryController extends AdminCategoryController
{
    protected string $model = ProductCategory::class;

    protected string $title = 'Categorie prodotti';

    protected string $routePrefix = 'admin.product_categories';

    /** La barra laterale del catalogo mostra solo categorie e sottocategorie. */
    protected int $maxLevels = 2;
}
