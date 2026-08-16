<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtisanPortfolioItem extends Model
{
    protected $fillable = [
        'artisan_id',
        'image_path',
        'caption',
    ];

    public function artisan()
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }
}
