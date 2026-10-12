<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use App\Models\Conversion;
use App\Support\ContactGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Il modulo contatti non deve spedire i messaggi dei programmi che lo
 * compilano da soli, ne' farli contare come obiettivi.
 */
class ContactSpamTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\AdminSetting::current()->forceFill(['website_email' => 'info@ksm.test'])->save();
    }

    private function send(array $extra = [], bool $guard = true): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['User-Agent' => self::CHROME])->post('/contatti', array_merge(
            ['name' => 'Anna', 'email' => 'anna@example.test', 'message' => 'Buongiorno, vorrei informazioni.'],
            $guard ? ContactGuard::passing() : [],
            $extra
        ));
    }

    public function test_il_modulo_porta_i_campi_di_difesa(): void
    {
        $this->get('/contatti')->assertOk()
            ->assertSee('name="website"', false)
            ->assertSee('name="_cg"', false);
    }

    public function test_un_invio_regolare_parte_e_conta_come_obiettivo(): void
    {
        Mail::fake();

        $this->send()->assertRedirect()->assertSessionHas('success');

        Mail::assertSent(ContactMessage::class);
        $this->assertSame(1, Conversion::where('goal', 'contact')->count());
    }

    public function test_il_campo_trappola_scarta_in_silenzio(): void
    {
        Mail::fake();

        $this->send(['website' => 'http://spam.example'])->assertRedirect()->assertSessionHas('success');

        Mail::assertNothingSent();
        $this->assertSame(0, Conversion::count());
    }

    public function test_senza_il_gettone_si_chiede_di_ricaricare(): void
    {
        Mail::fake();

        $this->send(guard: false)->assertRedirect()->assertSessionHasErrors('message');

        Mail::assertNothingSent();
        $this->assertSame(0, Conversion::count());
    }

    public function test_un_invio_troppo_veloce_o_con_gettone_vecchio_o_falso_non_parte(): void
    {
        Mail::fake();

        $this->send([ContactGuard::TOKEN => ContactGuard::token(time())])->assertSessionHasErrors('message');
        $this->send([ContactGuard::TOKEN => ContactGuard::token(time() - 8 * 86400)])->assertSessionHasErrors('message');
        $this->send([ContactGuard::TOKEN => 'inventato'])->assertSessionHasErrors('message');

        Mail::assertNothingSent();
    }

    public function test_troppi_link_nel_messaggio_sono_spam(): void
    {
        Mail::fake();

        $this->send(['message' => 'guarda http://a.test http://b.test e www.c.test'])->assertSessionHas('success');

        Mail::assertNothingSent();
    }

    public function test_il_limite_di_invii_per_indirizzo(): void
    {
        Mail::fake();

        foreach (range(1, 6) as $i) {
            $this->send()->assertStatus(302);
        }

        $this->send()->assertStatus(429);
    }
}
