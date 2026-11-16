<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Domain extends Model
{
    use Concerns\HasDomainConnection;

    /** Colonna con il nome del dominio, per lo stato di collegamento. */
    public function domainColumn(): string
    {
        return 'domain';
    }

    /** Ricavato dal filtro a ogni salvataggio, per l'elenco: home | category | city | category+city */
    public const TYPES = ['home', 'category', 'city', 'category+city'];

    /** Cosa apre l'indirizzo principale del dominio. */
    public const ENTRY_PAGES = [
        'home' => 'Home',
        'shop' => 'Shop prodotti',
        'companies' => 'Elenco aziende',
        'company' => 'Pagina di un\'azienda',
        'page' => 'Pagina CMS',
    ];

    /** Di chi sono i prodotti che compaiono nel sito del dominio. */
    public const PRODUCT_SCOPES = [
        'companies' => 'Solo delle aziende mostrate',
        'all' => 'Di tutto il marketplace',
        'none' => 'Nessun prodotto',
    ];

    protected $fillable = [
        'name', 'domain', 'type',
        'company_category_ids', 'product_category_ids', 'companies_with_products', 'product_scope', 'filter_place',
        'logo', 'city', 'address', 'phone', 'email', 'social_links',
        'description', 'is_active',
        'header_variant', 'header_background', 'header_color', 'header_accent',
        'header_tagline', 'header_subline',
        'entry_page', 'entry_company_id', 'entry_cms_page_id', 'favicon', 'site',
    ];

    protected $casts = [
        'social_links' => 'array',
        'site' => 'array',
        'is_active' => 'boolean',
        'company_category_ids' => 'array',
        'product_category_ids' => 'array',
        'companies_with_products' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Categorie come numeri senza doppioni, luogo ripulito, e il tipo che l'elenco mostra.
        static::saving(function (self $domain) {
            foreach (['company_category_ids', 'product_category_ids'] as $field) {
                $domain->$field = self::cleanIds($domain->$field) ?: null;
            }

            $domain->filter_place = trim((string) $domain->filter_place) ?: null;
            $domain->product_scope = isset(self::PRODUCT_SCOPES[$domain->product_scope]) ? $domain->product_scope : 'companies';

            $byCategory = $domain->company_category_ids || $domain->product_category_ids;
            $domain->type = match (true) {
                $byCategory && $domain->filter_place !== null => 'category+city',
                $byCategory => 'category',
                $domain->filter_place !== null => 'city',
                default => 'home',
            };
        });

        // Le aziende che stavano solo qui si spengono: senza, finirebbero su KSM e sugli altri domini.
        // Chi e' esclusiva anche di un altro dominio resta accesa e resta li'.
        static::deleting(function (self $domain) {
            $pivot = DB::table('company_exclusive_domain');

            Company::query()
                ->whereIn('id', (clone $pivot)->where('domain_id', $domain->getKey())->select('company_id'))
                ->whereNotIn('id', (clone $pivot)->where('domain_id', '!=', $domain->getKey())->select('company_id'))
                ->update(['is_active' => false]);

            (clone $pivot)->where('domain_id', $domain->getKey())->delete();
        });
    }

    public function entryCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'entry_company_id');
    }

    public function entryCmsPage(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'entry_cms_page_id');
    }

    /**
     * Il filtro a blocchi che da' lo stesso risultato dei campi di prima
     * (type, company_category_id, product_category_id, company_scope, city).
     *
     * Le categorie contavano solo con il tipo "per categoria", il luogo
     * solo con "per citta'"; con "solo chi vende" la categoria azienda non
     * contava per niente.
     *
     * @param  array<string, mixed>  $row
     * @return array{company_category_ids: list<int>, product_category_ids: list<int>, companies_with_products: bool, product_scope: string, filter_place: ?string}
     */
    public static function filterFromLegacy(array $row): array
    {
        $type = (string) ($row['type'] ?? 'home');
        $scope = (string) ($row['company_scope'] ?? 'category');
        $byCategory = in_array($type, ['category', 'category+city'], true);
        $byCity = in_array($type, ['city', 'category+city'], true);

        return [
            'company_category_ids' => $byCategory && $scope !== 'products' ? self::cleanIds([$row['company_category_id'] ?? null]) : [],
            'product_category_ids' => $byCategory ? self::cleanIds([$row['product_category_id'] ?? null]) : [],
            'companies_with_products' => in_array($scope, ['products', 'both'], true),
            'product_scope' => 'companies',
            'filter_place' => $byCity ? (trim((string) ($row['city'] ?? '')) ?: null) : null,
        ];
    }

    /** @return list<int> */
    private static function cleanIds(mixed $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', (array) $ids), fn (int $id) => $id > 0)));
    }
}
