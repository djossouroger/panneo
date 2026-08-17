<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'is_active',
        'profile_photo_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'profile_photo_path',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path ? url('storage/'.$this->profile_photo_path) : null;
    }

    public function artisanProfile()
    {
        return $this->hasOne(ArtisanProfile::class);
    }

    public function repairRequests()
    {
        return $this->hasMany(RepairRequest::class, 'client_id');
    }

    public function acceptedRepairRequests()
    {
        return $this->hasMany(RepairRequest::class, 'accepted_artisan_id');
    }

    public function repairRequestOffers()
    {
        return $this->hasMany(RepairRequestOffer::class, 'artisan_id');
    }

    public function reviewsReceived()
    {
        return $this->hasMany(Review::class, 'artisan_id');
    }

    public function reviewsWritten()
    {
        return $this->hasMany(Review::class, 'client_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications()
    {
        return $this->hasMany(Notification::class)->whereNull('read_at');
    }

    public function reviews()
    {
        return $this->reviewsReceived();
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'artisan_category', 'artisan_id', 'category_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function serviceAreas()
    {
        return $this->hasMany(ArtisanServiceArea::class, 'artisan_id');
    }

    public function workingHours()
    {
        return $this->hasMany(ArtisanWorkingHour::class, 'artisan_id')->orderBy('day_of_week');
    }

    public function unavailabilities()
    {
        return $this->hasMany(ArtisanUnavailability::class, 'artisan_id');
    }

    public function portfolioItems()
    {
        return $this->hasMany(ArtisanPortfolioItem::class, 'artisan_id');
    }

    public function verificationSubmissions()
    {
        return $this->hasMany(ArtisanVerificationSubmission::class, 'artisan_id');
    }

    public function favoriteArtisans()
    {
        return $this->belongsToMany(User::class, 'favorite_artisans', 'client_id', 'artisan_id')
            ->withTimestamps();
    }

    public function favoredByClients()
    {
        return $this->belongsToMany(User::class, 'favorite_artisans', 'artisan_id', 'client_id');
    }

    public function sessions()
    {
        return $this->hasMany(PersonalAccessToken::class, 'tokenable_id')->where('tokenable_type', self::class);
    }

    public function averageRating()
    {
        $avg = $this->reviewsReceived()->avg('rating');

        return $avg !== null ? (float) $avg : null;
    }

    public function reviewsCount()
    {
        return $this->reviewsReceived()->count();
    }

    public function completedInterventionsCount()
    {
        return RepairRequest::where('accepted_artisan_id', $this->id)
            ->where('status', RepairRequest::STATUS_COMPLETED)
            ->count();
    }
}
