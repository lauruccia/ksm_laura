<?php

namespace App\Http\Controllers\Admin;

use App\Models\CompanyCategory;

class AdminCompanyCategoryController extends AdminCategoryController
{
    protected string $model = CompanyCategory::class;

    protected string $title = 'Categorie aziende';

    protected string $routePrefix = 'admin.company_categories';

    /** Il sito originale ha categorie aziende anche al terzo livello. */
    protected int $maxLevels = 3;
}
