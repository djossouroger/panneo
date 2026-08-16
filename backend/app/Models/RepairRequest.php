<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepairRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_AWAITING_ARTISAN = 'awaiting_artisan';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'reference',
        'client_id',
        'category_id',
        'title',
        'description',
        'city',
        'district',
        'address_details',
        'images',
        'status',
        'accepted_artisan_id',
        'accepted_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'images' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function acceptedArtisan()
    {
        return $this->belongsTo(User::class, 'accepted_artisan_id');
    }

    public function offers()
    {
        return $this->hasMany(RepairRequestOffer::class);
    }

    public function activeOffer()
    {
        return $this->hasOne(RepairRequestOffer::class)->where('status', RepairRequestOffer::STATUS_PENDING)->latestOfMany();
    }

    public function latestOffer()
    {
        return $this->hasOne(RepairRequestOffer::class)->latestOfMany();
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAwaitingArtisan(): bool
    {
        return $this->status === self::STATUS_AWAITING_ARTISAN;
    }

    public function canBeCancelledByClient(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_AWAITING_ARTISAN], true);
    }

    public function assignReference(): void
    {
        if ($this->reference) {
            return;
        }

        $this->forceFill([
            'reference' => sprintf('PAN-%s-%06d', now()->year, $this->id),
        ])->save();
    }
}
