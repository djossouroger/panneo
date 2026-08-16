<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'is_active',
        'indicative_min_price',
        'indicative_max_price',
        'callout_fee_label',
        'callout_fee',
        'currency',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function artisanProfiles()
    {
        return $this->hasMany(ArtisanProfile::class);
    }

    public function artisans()
    {
        return $this->belongsToMany(User::class, 'artisan_category', 'category_id', 'artisan_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function repairRequests()
    {
        return $this->hasMany(RepairRequest::class);
    }
}