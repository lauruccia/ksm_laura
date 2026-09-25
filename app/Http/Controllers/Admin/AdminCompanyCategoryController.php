<?php

namespace App\Http\Controllers\Admin;

use App\Models\CompanyCategory;
use App\Support\CategoryIcon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCompanyCategoryController extends AdminCategoryController
{
    protected string $model = CompanyCategory::class;

    protected string $title = 'Categorie aziende';

    protected string $routePrefix = 'admin.company_categories';

    /** Il sito originale ha categorie aziende anche al terzo livello. */
    protected int $maxLevels = 3;

    protected string $itemsRelation = 'companies';

    protected string $itemsLabel = 'aziende';

    protected string $itemLabel = 'azienda';

    protected function fields(): array
    {
        return parent::fields() + [
            'icon' => [
                'label' => 'Icona',
                'type' => 'select',
                'empty' => 'Automatica',
                'hint' => 'Compare sui biglietti delle aziende. Automatica: quella della categoria superiore.',
            ],
        ];
    }

    protected function formData(): array
    {
        return parent::formData() + ['icon' => CategoryIcon::CHOICES];
    }

    protected function rules(Request $request, ?Model $record = null): array
    {
        return parent::rules($request, $record) + [
            'icon' => ['nullable', Rule::in(array_keys(CategoryIcon::CHOICES))],
        ];
    }
}
