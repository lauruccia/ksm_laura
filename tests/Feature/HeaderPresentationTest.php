<?php
namespace Tests\Feature;

use App\Models\Domain;
use App\Support\HeaderPresentation;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class HeaderPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_brand_and_shop_header_are_rendered(): void
    {
        Domain::create(['name' => 'Mozzarelle di bufala', 'domain' => 'bufala.test', 'type' => 'home', 'is_active' => true,
            'header_variant' => 'artisan', 'header_accent' => '#123456']);
        $this->get('http://bufala.test/')->assertOk()->assertSee('brand-header--artisan')
            ->assertSee('Mozzarelle di bufala')->assertSee('--brand-green:#123456')
            ->assertSee('action="http://bufala.test/prodotti"', false);
        $this->get('http://localhost/')->assertOk()->assertSee('brand-header--marketplace')
            ->assertDontSee('--brand-green:#123456');
    }

    public function test_page_rules_override_domain_and_invalid_styles_are_discarded(): void
    {
        app(TenantContext::class)->useDomain(new Domain(['name' => 'Brand', 'header_variant' => 'artisan', 'header_accent' => 'red;display:none']));
        config(['header.pages' => [['path' => 'prodotti*', 'variant' => 'shop']]]);
        $resolver = app(HeaderPresentation::class);
        $result = $resolver->resolve(Request::create('https://bufala.test/prodotti'));
        $this->assertSame('shop', $result['variant']);
        $this->assertSame('', $result['style']);
        $this->assertSame('marketplace', $resolver->resolve(Request::create('/'), 'invalid')['variant']);
    }
}
