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

    /** home | category | city | category+city | company */
    public const TYPES = ['home', 'category', 'city', 'category+city', 'company'];

    /** Cosa apre l'indirizzo principale del dominio. */
    public const ENTRY_PAGES = [
        'home' => 'Home',
        'shop' => 'Shop prodotti',
        'companies' => 'Elenco aziende',
        'company' => 'Pagina di un\'azienda',
        'page' => 'Pagina CMS',
    ];

    /** Quali aziende compaiono nel sito del dominio. */
    public const COMPANY_SCOPES = [
        'category' => 'Della categoria azienda',
        'products' => 'Solo chi vende prodotti nella categoria prodotto',
        'both' => 'Della categoria azienda e con prodotti nella categoria prodotto',
    ];

    protected $fillable = [
        'name', 'domain', 'type', 'company_category_id', 'product_category_id',
        'logo', 'city', 'address', 'phone', 'email', 'social_links',
        'description', 'is_active',
        'header_variant', 'header_background', 'header_color', 'header_accent',
        'header_tagline', 'header_subline',
        'entry_page', 'entry_company_id', 'entry_cms_page_id', 'company_scope', 'favicon', 'site',
    ];

    protected $casts = [
        'social_links' => 'array',
        'site' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
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

    public function companyCategory(): BelongsTo
    {
        return $this->belongsTo(CompanyCategory::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function entryCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'entry_company_id');
    }

    public function entryCmsPage(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'entry_cms_page_id');
    }

    public function filtersByCategory(): bool
    {
        return in_array($this->type, ['category', 'category+city'], true);
    }

    public function filtersByCity(): bool
    {
        return in_array($this->type, ['city', 'category+city'], true);
    }
}
