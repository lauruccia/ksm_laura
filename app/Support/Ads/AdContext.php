<?php

namespace App\Support\Ads;

use App\Models\CompanyCategory;
use App\Support\TenantContext;

/**
 * Dove si trova il visitatore, per scegliere i banner mirati.
 *
 * Il dominio viene dal contesto della richiesta; citta' e categoria dalla
 * pagina, se le conosce, altrimenti dal dominio della rete. Una categoria
 * porta con se' tutte le sue madri: una campagna su "Mangiare e bere"
 * compare anche nelle pagine dei ristoranti.
 */
final class AdContext
{
    /** Il sito principale, ksm.it, nei bersagli delle campagne. */
    public const MAIN_SITE = 0;

    /** @param  list<int>  $categoryIds */
    public function __construct(
        public readonly int $domainId,
        public readonly ?string $city,
        public readonly array $categoryIds,
    ) {
    }

    public static function current(?string $city = null, ?int $categoryId = null): self
    {
        $domain = app(TenantContext::class)->domain();
        $categoryId ??= $domain?->company_category_id;

        // Tutte le madri, non solo la prima: le categorie aziende arrivano al terzo livello.
        $categoryIds = [];

        for ($id = $categoryId; $id && ! in_array((int) $id, $categoryIds, true); $id = CompanyCategory::whereKey($id)->value('parent_id')) {
            $categoryIds[] = (int) $id;
        }

        return new self(
            domainId: $domain?->id ?? self::MAIN_SITE,
            city: filled($city) ? $city : $domain?->city,
            categoryIds: $categoryIds,
        );
    }
}
