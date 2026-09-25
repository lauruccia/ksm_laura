<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SmtpSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminMailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_posta_scritta_in_amministrazione_prende_il_posto_del_env(): void
    {
        $this->actingAs($this->admin())->get(route('admin.settings.edit'))->assertOk()->assertSee('name="mail_encryption"', false);

        $this->actingAs($this->admin())->put(route('admin.settings.mail'), [
            'mail_mailer' => 'smtp',
            'mail_host' => 'mail.example.test',
            'mail_port' => 465,
            'mail_username' => 'info@example.test',
            'mail_password' => 'segreta',
            'mail_encryption' => 'ssl',
            'mail_from_address' => 'info@example.test',
            'mail_from_name' => 'KSM',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        // In chiaro nel database non c'e'.
        $this->assertNotSame('segreta', DB::table('smtp_settings')->value('mail_password'));
        $this->assertSame('segreta', SmtpSetting::first()->mail_password);

        $this->app->forgetInstance('mail.manager');
        $this->app->make('mail.manager');

        $this->assertSame('mail.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('segreta', config('mail.mailers.smtp.password'));
        $this->assertSame('info@example.test', config('mail.from.address'));
    }

    public function test_spenta_valgono_quelle_del_env(): void
    {
        SmtpSetting::create(['owner_type' => 'admin', 'mail_host' => 'mail.example.test', 'mail_port' => 25, 'is_active' => false]);
        $host = config('mail.mailers.smtp.host');

        $this->app->forgetInstance('mail.manager');
        $this->app->make('mail.manager');

        $this->assertSame($host, config('mail.mailers.smtp.host'));
    }

    public function test_accesa_servono_server_e_porta(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.mail'), [
            'mail_mailer' => 'smtp',
            'is_active' => '1',
        ])->assertSessionHasErrors(['mail_host', 'mail_port']);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super amministratore', 'is_system' => true]);

        return User::create([
            'name' => 'Amministratore', 'email' => 'admin'.uniqid().'@example.test', 'password' => 'password',
            'user_type' => 'admin', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }
}
