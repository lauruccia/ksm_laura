<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Domain;

/**
 * Contesto del dominio corrente, condiviso con le viste.
 * Unico punto in cui si decide logo, filtri e recapiti da mostrare.
 */
class TenantContext
{
    private ?Company $company = null;

    private ?Domain $domain = null;

    public function useCompany(Company $company): void
    {
        $this->company = $company;
        $this->share();
    }

    public function useDomain(Domain $domain): void
    {
        $this->domain = $domain;
        $this->share();
    }

    public function reset(): void
    {
        $this->company = null;
        $this->domain = null;
        $this->share();
    }

    public function company(): ?Company
    {
        return $this->company;
    }

    public function domain(): ?Domain
    {
        return $this->domain;
    }

    public function isCompanySite(): bool
    {
        return $this->company !== null;
    }

    /** Nome mostrato nell'intestazione. */
    public function brandName(): string
    {
        return $this->company?->name
            ?? $this->domain?->name
            ?? config('ksm.brand_name');
    }

    public function brandLogo(): ?string
    {
        return $this->company?->logo
            ?? $this->domain?->logo
            ?? null;
    }

    /** Filtri impliciti da applicare agli elenchi di aziende. */
    public function listingFilters(): array
    {
        $filters = [];

        if ($this->domain?->filtersByCategory()) {
            $filters['category'] = $this->domain->company_category_id;
        }

        if ($this->domain?->filtersByCity()) {
            $filters['city'] = $this->domain->city;
        }

        return array_filter($filters);
    }

    private function share(): void
    {
        view()->share('tenant', $this);
    }
}
