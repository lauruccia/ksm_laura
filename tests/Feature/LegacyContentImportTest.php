<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\CmsPage;
use App\Models\Domain;
use App\Support\Ads\Placements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Domini, pagine CMS e banner dal dump del sito originale.
 */
class LegacyContentImportTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'dump');

        file_put_contents($this->path, <<<'SQL'
        INSERT INTO `domains` (`id`, `company_category_id`, `product_category_id`, `name`, `domain`, `type`, `logo`, `city`, `address`, `phone`, `email`, `social_links`, `domain_verified`, `ssl_issued`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
        (8, NULL, NULL, 'quartieriroma.it', 'quartieriroma.it', 'city', 'uploads/domains/logo.png', 'Roma', 'via Eurialo 56, Roma', '036', 'info@ksm.it', NULL, 1, 1, NULL, 1, '2026-03-23 10:54:58', '2026-03-25 14:50:23'),
        (9, 125, 21, 'ristoranticalabria.com', 'ristoranticalabria.com', 'category+city', NULL, 'Calabria', NULL, NULL, NULL, NULL, 1, 1, NULL, 1, '2026-03-25 11:24:43', '2026-03-25 14:44:25');
        INSERT INTO `advertisements` (`id`, `name`, `link`, `clicks`, `img`, `locations`, `status`, `created_at`, `updated_at`) VALUES
        (13, 'kmoney', 'https://kmoney.it/register', 2927, 'uploads/advertisements/ad.png', '[\"store_list_above_stores\"]', 1, '2025-12-24 10:30:57', '2026-09-14 06:15:39');
        INSERT INTO `cms_pages` (`id`, `title`, `slug`, `content`, `status`, `locations`, `visibility`, `sort_order`, `include_in_sitemap`, `created_at`, `updated_at`) VALUES
        (1, 'privacy policy', 'privacy-policy', '<p>Ultimo aggiornamento: 01/12/2025</p>', 'published', '[\"footer\"]', 'visible', 0, 1, '2025-10-21 01:02:19', '2025-10-21 01:02:19');
        SQL);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    public function test_importa_domini_pagine_e_banner_con_gli_id_originali(): void
    {
        DB::table('company_categories')->insert(['id' => 125, 'name' => 'Ristoranti e pizzerie', 'slug' => 'ristoranti-e-pizzerie']);

        $this->artisan('legacy:import-content', ['file' => $this->path])->assertSuccessful();

        $this->assertSame('quartieriroma.it', Domain::findOrFail(8)->domain);

        $calabria = Domain::findOrFail(9);
        $this->assertSame(125, (int) $calabria->company_category_id);
        $this->assertNull($calabria->product_category_id);

        $kmoney = Advertisement::findOrFail(13);
        $this->assertSame(2927, $kmoney->clicks);
        $this->assertSame([Placements::COMPANIES_LIST], $kmoney->locations);
        $this->assertTrue($kmoney->isRunning());

        $this->assertSame('published', CmsPage::where('slug', 'privacy-policy')->value('status'));
    }

    public function test_non_importa_sopra_tabelle_gia_piene(): void
    {
        $this->artisan('legacy:import-content', ['file' => $this->path])->assertSuccessful();
        $this->artisan('legacy:import-content', ['file' => $this->path])->assertFailed();

        $this->assertSame(2, Domain::count());
    }
}
