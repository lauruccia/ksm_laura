<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function scopeForAdmin($query)
    {
        return $query->where('owner_type', 'admin');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('owner_type', 'company')->where('owner_id', $companyId);
    }
}
