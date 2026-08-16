<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtisanWorkingHour extends Model
{
    protected $fillable = [
        'artisan_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_working_day',
    ];

    protected $casts = [
        'is_working_day' => 'boolean',
    ];

    public function artisan()
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }
}
