<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    public const TYPE_BEHAVIOR = 'behavior';
    public const TYPE_SERVICE_QUALITY = 'service_quality';
    public const TYPE_NO_SHOW = 'no_show';
    public const TYPE_SAFETY = 'safety';
    public const TYPE_OTHER = 'other';

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'repair_request_id',
        'reporter_id',
        'subject',
        'description',
        'type',
        'status',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'admin_notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function participantUserIds(): array
    {
        $ids = [$this->repairRequest?->client_id, $this->repairRequest?->accepted_artisan_id];

        return array_values(array_filter(array_unique($ids)));
    }
}
