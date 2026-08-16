<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtisanVerificationDocument extends Model
{
    protected $fillable = [
        'submission_id',
        'document_type',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function submission()
    {
        return $this->belongsTo(ArtisanVerificationSubmission::class, 'submission_id');
    }
}
