<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaVerificationDocument extends Model
{
    protected $fillable = [
        'spa_id',
        'document_type',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
    ];

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }
}