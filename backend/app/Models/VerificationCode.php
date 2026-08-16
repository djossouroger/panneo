<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificationCode extends Model
{
    public const PURPOSE_PHONE_VERIFY = 'phone_verify';
    public const PURPOSE_EMAIL_VERIFY = 'email_verify';
    public const PURPOSE_EMAIL_CHANGE = 'email_change';
    public const PURPOSE_PHONE_CHANGE = 'phone_change';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    protected $fillable = [
        'user_id',
        'purpose',
        'channel',
        'recipient',
        'code_hash',
        'expires_at',
        'attempts',
        'last_sent_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
