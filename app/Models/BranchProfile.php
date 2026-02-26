<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchProfile extends Model
{
    use HasFactory;
    protected $fillable = [
        'branch_id',
        'is_listed',
        'cover_image',
        'description',
        'gallery_images',
        'phone',
        'opening_time',
        'closing_time'
    ];

    protected $casts = [
        'gallery_images' => 'array',
        'is_listed' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
