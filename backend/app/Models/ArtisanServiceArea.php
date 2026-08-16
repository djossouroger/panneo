<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtisanServiceArea extends Model
{
    protected $fillable = [
        'artisan_id',
        'city',
        'district',
    ];

    public function artisan()
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }
}
