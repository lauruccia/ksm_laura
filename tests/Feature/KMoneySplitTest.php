<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyPaymentSetting;
use App\Models\KMoneyCategoryRule;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Payments\KMoney\CheckoutSplit;
use App\Payments\KMoney\KMoneyPercentages;
use App\Payments\KMoney\KMoneyShare;
use App\Payments\KMoney\KMoneySplitter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chi decide la quota KMoney, e come si divide un carrello.
 */
class KMoneySplitTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private CompanyPaymentSetting $settings;

    private ProductCategory $vini;

    private ProductCategory $olio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->company('Calabria Sapori');
        $this->settings = CompanyPaymentSetting::create([
            'company_id' => $this->company->id,
            'enable_kmoney' => true,
            'kmoney_api_token' => 'km_test_token',
        ]);
        $this->settings->forceFill(['kmoney_contract_percent' => 50])->save();

        $this->vini = ProductCategory::create(['name' => 'Vini', 'slug' => 'vini']);
        $this->olio = ProductCategory::create(['name' => 'Olio', 'slug' => 'olio']);
    }

    private function company(string $name): Company
    {
        $vendor = User::create([
            'name' => 'Venditore', 'email' => 'venditore'.uniqid().'@example.test',
            'password' => 'password', 'user_type' => 'vendor',
        ]);

        return Company::create([
            'user_id' => $vendor->id, 'name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'is_active' => true,
        ]);
    }

    private function product(float $price, array $attributes = [], ?Company $company = null): Product
    {
        return Product::create($attributes + [
            'company_id' => ($company ?? $this->company)->id,
            'name' => 'Prodotto',
            'slug' => 'prodotto-'.uniqid(),
            'price' => $price,
            'stock' => 10,
            'status' => 'active',
        ]);
    }

    /** @param  list<array{0: Product, 1?: int}>  $lines */
    private function split(array $lines, float $shipping = 0, bool $allEuro = false): CheckoutSplit
    {
        $items = collect($lines)->map(fn ($line) => [
            'product_id' => $line[0]->id,
            'price' => (float) $line[0]->price,
            'quantity' => $line[1] ?? 1,
        ]);

        return app(KMoneySplitter::class)->split($this->company->fresh('paymentSettings'), $items, $shipping, $allEuro);
    }

    public function test_le_quote_consentite_sono_cinque(): void
    {
        $this->assertSame(25, KMoneyShare::snap(30));
        $this->assertSame(100, KMoneyShare::snap(120));
        $this->assertSame(0, KMoneyShare::snap(-5));
        $this->assertSame(75, KMoneyShare::snap('75.00'));
    }

    public function test_la_scelta_sul_prodotto_vince_sulla_categoria_e_la_categoria_sul_contratto(): void
    {
        KMoneyCategoryRule::create([
            'company_id' => $this->company->id, 'product_category_id' => $this->vini->id, 'percent' => 25,
        ]);

        $scelto = $this->product(10, ['category_id' => $this->vini->id, 'kmoney_discount_percent' => 75]);
        $diCategoria = $this->product(10, ['category_id' => $this->vini->id]);
        $diContratto = $this->product(10, ['category_id' => $this->olio->id]);

        $split = $this->split([[$scelto], [$diCategoria], [$diContratto]]);

        $this->assertSame(75, $split->percentFor($scelto->id));
        $this->assertSame(25, $split->percentFor($diCategoria->id));
        $this->assertSame(50, $split->percentFor($diContratto->id));
    }

    public function test_con_il_conto_in_debito_si_paga_tutto_in_kmoney(): void
    {
        $this->settings->forceFill(['kmoney_in_debt' => true])->save();
        $product = $this->product(10, ['kmoney_discount_percent' => 25]);

        $this->assertSame(100, $this->split([[$product]])->percentFor($product->id));
    }

    public function test_senza_kmoney_collegato_la_quota_scelta_resta(): void
    {
        // Non si converte in euro da sola: e' la cassa a fermare l'ordine.
        $this->settings->update(['enable_kmoney' => false]);
        $product = $this->product(10, ['kmoney_discount_percent' => 100]);

        $split = $this->split([[$product]]);

        $this->assertSame(100, $split->percentFor($product->id));
        $this->assertTrue($split->hasKmoney());
    }

    public function test_senza_impostazioni_di_incasso_vale_solo_la_scelta_sul_prodotto(): void
    {
        $this->settings->delete();
        $scelto = $this->product(10, ['kmoney_discount_percent' => 50]);
        $libero = $this->product(10);

        $split = $this->split([[$scelto], [$libero]]);

        $this->assertSame(50, $split->percentFor($scelto->id));
        $this->assertSame(0, $split->percentFor($libero->id));
    }

    public function test_la_spedizione_segue_la_quota_piu_bassa_del_carrello(): void
    {
        $tutto = $this->product(10, ['kmoney_discount_percent' => 100]);
        $unQuarto = $this->product(20, ['kmoney_discount_percent' => 25]);

        $split = $this->split([[$tutto], [$unQuarto]], shipping: 8);

        $this->assertSame(25, $split->shippingPercent);
        $this->assertSame(17.0, $split->kmoney());
        $this->assertSame(21.0, $split->euro());
    }

    public function test_i_centesimi_non_si_perdono(): void
    {
        $product = $this->product(9.99, ['kmoney_discount_percent' => 25]);

        $split = $this->split([[$product, 3]]);

        $this->assertSame(2997, $split->totalCents);
        $this->assertSame(749, $split->kmoneyCents);
        $this->assertSame(2248, $split->euroCents());
    }

    public function test_tutto_in_euro_azzera_la_quota(): void
    {
        $product = $this->product(10, ['kmoney_discount_percent' => 100]);

        $split = $this->split([[$product]], shipping: 5, allEuro: true);

        $this->assertFalse($split->hasKmoney());
        $this->assertSame(15.0, $split->euro());
    }

    public function test_il_ricalcolo_scrive_la_quota_effettiva_solo_sui_prodotti_dell_azienda(): void
    {
        KMoneyCategoryRule::create([
            'company_id' => $this->company->id, 'product_category_id' => $this->vini->id, 'percent' => 25,
        ]);
        $vino = $this->product(10, ['category_id' => $this->vini->id]);
        $olio = $this->product(10, ['category_id' => $this->olio->id]);
        $altrui = $this->product(10, [], $this->company('Altra azienda'));

        $percentages = app(KMoneyPercentages::class);
        $percentages->refresh($this->company);

        $this->assertSame(25, $vino->fresh()->kmoney_percent);
        $this->assertSame(50, $olio->fresh()->kmoney_percent);

        $count = $percentages->setProductPercent($this->company, [$olio->id, $altrui->id], 100);

        $this->assertSame(1, $count);
        $this->assertSame(100, $olio->fresh()->kmoney_percent);
        $this->assertNull($altrui->fresh()->kmoney_discount_percent);
    }
}
