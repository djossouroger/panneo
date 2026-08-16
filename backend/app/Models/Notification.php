<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    public const TYPE_REPAIR_REQUEST_RECEIVED = 'repair_request_received';
    public const TYPE_REPAIR_REQUEST_ACCEPTED = 'repair_request_accepted';
    public const TYPE_REPAIR_REQUEST_REJECTED = 'repair_request_rejected';
    public const TYPE_REPAIR_REQUEST_STARTED = 'repair_request_started';
    public const TYPE_REPAIR_REQUEST_COMPLETED = 'repair_request_completed';
    public const TYPE_REVIEW_RECEIVED = 'review_received';
    public const TYPE_ACCOUNT_VERIFIED = 'account_verified';
    public const TYPE_ARTISAN_ACCOUNT_VERIFIED = 'artisan_account_verified';
    public const TYPE_VERIFICATION_REJECTED = 'verification_rejected';
    public const TYPE_DISPUTE_STATUS_UPDATED = 'dispute_status_updated';

    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if ($this->isRead()) {
            return;
        }

        $this->forceFill(['read_at' => now()])->save();
    }
}
