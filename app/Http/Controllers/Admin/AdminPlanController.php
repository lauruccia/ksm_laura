<?php

namespace App\Http\Controllers\Admin;

use App\Models\Plan;
use App\Support\PlanCapabilities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminPlanController extends AdminResourceController
{
    protected string $model = Plan::class;

    protected string $title = 'Piani';

    protected string $routePrefix = 'admin.plans';

    protected function columns(): array
    {
        return ['name' => 'Nome', 'price' => 'Prezzo', 'priority' => 'Ordine'];
    }

    protected function formData(): array
    {
        return ['capabilities' => PlanCapabilities::all()];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Nome', 'type' => 'text'],
            'slug' => ['label' => 'Slug', 'type' => 'text', 'hint' => 'Lascia vuoto per generarlo dal nome'],
            'description' => ['label' => 'Descrizione', 'type' => 'textarea'],
            'price' => ['label' => 'Prezzo', 'type' => 'number', 'step' => '0.01', 'hint' => 'Quota per periodo, IVA inclusa'],
            'duration_days' => ['label' => 'Durata in giorni', 'type' => 'number', 'hint' => '365 per un piano annuale. Vuoto: il piano non scade mai'],
            'priority' => [
                'label' => 'Ordine',
                'type' => 'number',
                'hint' => 'Comanda la posizione nella directory: il numero piu alto viene prima',
            ],
            'capabilities' => [
                'label' => 'Cosa permette il piano',
                'type' => 'checkboxes',
                'hint' => 'Spunta solo cio che questo piano concede davvero',
            ],
            'features' => ['label' => 'Voci mostrate in vetrina', 'type' => 'list'],
            'is_active' => ['label' => 'Piano proponibile alle aziende', 'type' => 'checkbox'],
        ];
    }

    protected function rules(Request $request, ?Model $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($record)],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'priority' => ['required', 'integer', 'min:0', 'max:127'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string', Rule::in(PlanCapabilities::keys())],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }

    protected function transform(array $data, Request $request, ?Model $record = null): array
    {
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['features'] = array_values(array_filter($data['features'] ?? []));
        $data['capabilities'] = PlanCapabilities::sanitize($data['capabilities'] ?? []);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
