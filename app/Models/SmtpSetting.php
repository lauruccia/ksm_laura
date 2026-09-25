<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SmtpSetting extends Model
{
    protected $fillable = [
        'owner_type', 'owner_id', 'mail_mailer', 'mail_host', 'mail_port',
        'mail_username', 'mail_password', 'mail_encryption',
        'mail_from_address', 'mail_from_name', 'reply_to_address',
        'reply_to_name', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = ['mail_password'];

    /**
     * La password si salva cifrata con la chiave dell'app. Quelle scritte
     * prima in chiaro si leggono ancora com'erano.
     */
    protected function mailPassword(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : rescue(fn () => Crypt::decryptString($value), $value, false),
            set: fn (?string $value) => $value === null ? null : Crypt::encryptString($value),
        );
    }

    /**
     * Se in Amministrazione la posta in uscita e' accesa, i suoi dati prendono
     * il posto di quelli del .env. Si legge solo quando si spedisce davvero.
     */
    public static function applyToMailer(): void
    {
        $mail = rescue(fn () => static::query()->forAdmin()->where('is_active', true)->first(), null, false);

        if (! $mail || blank($mail->mail_host) || blank($mail->mail_port)) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $mail->mail_host,
            'mail.mailers.smtp.port' => (int) $mail->mail_port,
            'mail.mailers.smtp.username' => $mail->mail_username,
            'mail.mailers.smtp.password' => $mail->mail_password,
            // "ssl" e' la connessione cifrata dall'inizio; vuoto decide Symfony
            // (cifrata sulla 465, altrimenti STARTTLS se il server la offre).
            'mail.mailers.smtp.scheme' => $mail->mail_encryption === 'ssl' ? 'smtps' : null,
            'mail.mailers.smtp.url' => null,
        ]);

        if (filled($mail->mail_from_address)) {
            config(['mail.from.address' => $mail->mail_from_address]);
        }

        if (filled($mail->mail_from_name)) {
            config(['mail.from.name' => $mail->mail_from_name]);
        }
    }

    public function scopeForAdmin($query)
    {
        return $query->where('owner_type', 'admin');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('owner_type', 'company')->where('owner_id', $companyId);
    }
}
