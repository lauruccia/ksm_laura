<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Domain;
use App\Support\Sites\SiteContent;
use App\Support\Sites\SiteScope;

/**
 * Contesto del dominio corrente, condiviso con le viste.
 * Unico punto in cui si decide logo, filtri e recapiti da mostrare.
 */
class TenantContext
{
    private ?Company $company = null;

    private ?Domain $domain = null;

    private ?SiteScope $scope = null;

    private ?SiteContent $content = null;

    public function useCompany(Company $company): void
    {
        $this->company = $company;
        $this->share();
    }

    public function useDomain(Domain $domain): void
    {
        $this->domain = $domain;
        $this->brandMail($domain->name);
        $this->share();
    }

    public function reset(): void
    {
        $this->company = null;
        $this->domain = null;
        $this->brandMail(null);
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

    /** Un dominio della rete: deve sembrare un sito a se', senza il marchio KSM. */
    public function isNetworkSite(): bool
    {
        return $this->domain !== null;
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

    /** Prodotti e aziende visibili sul dominio corrente. */
    public function scope(): SiteScope
    {
        return $this->scope ??= new SiteScope($this->domain);
    }

    /** Testi, immagini, menu e piede del dominio, con i valori predefiniti del sito. */
    public function content(): SiteContent
    {
        return $this->content ??= new SiteContent($this->domain);
    }

    /**
     * Le email partite da un dominio della rete portano il suo nome: mittente,
     * intestazione e piede del modello di Laravel leggono app.name e
     * mail.from.name. L'indirizzo del mittente resta quello della piattaforma,
     * l'unico autorizzato a spedire (SPF, DKIM). Con null tornano i valori
     * originali, cosi' la richiesta dopo non eredita il nome.
     *
     * @var array{app: mixed, from: mixed}|null
     */
    private ?array $originalMailBrand = null;

    private function brandMail(?string $name): void
    {
        $this->originalMailBrand ??= ['app' => config('app.name'), 'from' => config('mail.from.name')];

        config([
            'app.name' => $name ?? $this->originalMailBrand['app'],
            'mail.from.name' => $name ?? $this->originalMailBrand['from'],
        ]);
    }

    private function share(): void
    {
        $this->scope = null;
        $this->content = null;
        view()->share('tenant', $this);
    }
}
