<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ArtisanProfile extends Model
{
    public const VERIFICATION_PENDING = 'pending';
    public const VERIFICATION_VERIFIED = 'verified';
    public const VERIFICATION_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'category_id',
        'city',
        'district',
        'description',
        'is_available',
        'verification_status',
        'profile_photo_path',
        'years_of_experience',
        'specialties',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'verified_at' => 'datetime',
        'years_of_experience' => 'integer',
        'specialties' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'artisan_category', 'artisan_id', 'category_id', 'user_id')
            ->withPivot('is_primary');
    }

    public function primaryCategory()
    {
        return $this->categories()->wherePivot('is_primary', true)->first();
    }

    public function serviceAreas()
    {
        return $this->hasMany(ArtisanServiceArea::class, 'artisan_id', 'user_id');
    }

    public function workingHours()
    {
        return $this->hasMany(ArtisanWorkingHour::class, 'artisan_id', 'user_id')->orderBy('day_of_week');
    }

    public function unavailabilities()
    {
        return $this->hasMany(ArtisanUnavailability::class, 'artisan_id', 'user_id');
    }

    public function portfolioItems()
    {
        return $this->hasMany(ArtisanPortfolioItem::class, 'artisan_id', 'user_id');
    }

    public function verificationSubmissions()
    {
        return $this->hasMany(ArtisanVerificationSubmission::class, 'artisan_id', 'user_id');
    }

    public function latestVerificationSubmission()
    {
        return $this->hasOne(ArtisanVerificationSubmission::class, 'artisan_id', 'user_id')->latestOfMany();
    }

    public function isVerified(): bool
    {
        return $this->verification_status === self::VERIFICATION_VERIFIED;
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', self::VERIFICATION_VERIFIED);
    }

    public function scopeNotRejected(Builder $query): Builder
    {
        return $query->where('verification_status', '!=', self::VERIFICATION_REJECTED);
    }
}
