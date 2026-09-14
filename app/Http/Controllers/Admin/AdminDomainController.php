<?php

namespace App\Http\Controllers\Admin;

use App\Models\CompanyCategory;
use App\Models\Domain;
use App\Models\ProductCategory;
use App\Support\Domains\DomainConnectionChecker;
use App\Support\Domains\HostName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminDomainController extends AdminResourceController
{
    protected string $model = Domain::class;

    protected string $title = 'Domini';

    protected string $routePrefix = 'admin.domains';

    protected function columns(): array
    {
        return ['name' => 'Nome', 'domain' => 'Dominio', 'type' => 'Tipo', 'connection_status' => 'Collegamento'];
    }

    protected function rowActions(): array
    {
        return [['Verifica ora', 'admin.domains.check', 'PATCH']];
    }

    /** Verifica subito DNS e certificato, senza aspettare il giro orario. */
    public function check(Domain $domain, DomainConnectionChecker $checker): RedirectResponse
    {
        $result = $checker->refresh($domain, $domain->domain);

        return $result->connected()
            ? back()->with('success', __(':dominio e collegato.', ['dominio' => $domain->domain]))
            : back()->with('error', $domain->domain.': '.$result->error);
    }

    protected function formData(): array
    {
        return [
            'type' => collect(Domain::TYPES)->mapWithKeys(fn ($t) => [$t => $t]),
            'header_variant' => ['marketplace' => 'Marketplace blu', 'shop' => 'Shop blu', 'artisan' => 'Shop artigianale verde'],
            'company_category_id' => CompanyCategory::orderBy('name')->pluck('name', 'id'),
            'product_category_id' => ProductCategory::orderBy('name')->pluck('name', 'id'),
        ];
    }

    protected function fields(): array
    {
        return [
            'name' => ['label' => 'Nome', 'type' => 'text'],
            'domain' => ['label' => 'Dominio', 'type' => 'text', 'hint' => 'Senza www e senza protocollo'],
            'type' => ['label' => 'Tipo', 'type' => 'select'],
            'header_variant' => ['label' => 'Stile testata', 'type' => 'select', 'empty' => 'Predefinito (marketplace)'],
            'header_background' => ['label' => 'Colore fascia', 'type' => 'text', 'hint' => 'HEX, ad esempio #104b65. Vuoto: colore della variante.'],
            'header_color' => ['label' => 'Colore testo e marchio', 'type' => 'text', 'hint' => 'HEX, ad esempio #07365d. Vuoto: colore della variante.'],
            'header_accent' => ['label' => 'Colore pulsanti e accenti', 'type' => 'text', 'hint' => 'HEX, ad esempio #51a52c. Vuoto: colore della variante.'],
            'header_tagline' => ['label' => 'Sottotitolo testata', 'type' => 'text'],
            'header_subline' => ['label' => 'Motto testata', 'type' => 'text'],
            'company_category_id' => ['label' => 'Categoria azienda', 'type' => 'select', 'empty' => 'Nessuna'],
            'product_category_id' => ['label' => 'Categoria prodotto', 'type' => 'select', 'empty' => 'Nessuna'],
            'city' => ['label' => 'Citta', 'type' => 'text'],
            'address' => ['label' => 'Indirizzo', 'type' => 'text'],
            'phone' => ['label' => 'Telefono', 'type' => 'text'],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'description' => ['label' => 'Descrizione', 'type' => 'textarea'],
            'is_active' => ['label' => 'Attivo', 'type' => 'checkbox'],
        ];
    }

    protected function rules(Request $request, ?Model $record = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255', Rule::unique('domains', 'domain')->ignore($record)],
            'type' => ['required', Rule::in(Domain::TYPES)],
            'header_variant' => ['nullable', Rule::in(['marketplace', 'shop', 'artisan'])],
            'header_background' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'header_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'header_accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'header_tagline' => ['nullable', 'string', 'max:255'],
            'header_subline' => ['nullable', 'string', 'max:255'],
            'company_category_id' => ['nullable', 'exists:company_categories,id'],
            'product_category_id' => ['nullable', 'exists:product_categories,id'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function transform(array $data, Request $request, ?Model $record = null): array
    {
        $data['domain'] = HostName::normalize($data['domain']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
