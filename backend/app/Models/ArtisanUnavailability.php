<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtisanUnavailability extends Model
{
    public const TYPE_PAUSE = 'pause';
    public const TYPE_LEAVE = 'leave';
    public const TYPE_TEMPORARY_UNAVAILABLE = 'temporary_unavailable';

    protected $fillable = [
        'artisan_id',
        'type',
        'starts_at',
        'ends_at',
        'reason',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function artisan()
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }

    public function isActiveAt(\DateTimeInterface $moment): bool
    {
        return $this->starts_at->lte($moment)
            && ($this->ends_at === null || $this->ends_at->gte($moment));
    }
}
