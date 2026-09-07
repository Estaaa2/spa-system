<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RatingPhoto extends Model
{
    protected $fillable = ['rating_id', 'path'];

    public function rating()
    {
        return $this->belongsTo(Rating::class);
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }
}
