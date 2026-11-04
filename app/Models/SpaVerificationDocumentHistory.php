<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaVerificationDocumentHistory extends Model
{
    protected $fillable = [
        'spa_id',
        'branch_id',
        'document_type',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'owner_expiry_date',
        'ocr_expiry_date',
        'expiry_date',
        'expiry_date_raw',
        'expiry_detection_status',
        'expiry_detection_source',
        'expiry_scanned_at',
        'expiry_verified_at',
        'expiry_verified_by',
        'replaced_at',
        'replaced_by',
    ];

    protected $casts = [
        'owner_expiry_date' => 'date',
        'ocr_expiry_date' => 'date',
        'expiry_date' => 'date',
        'expiry_scanned_at' => 'datetime',
        'expiry_verified_at' => 'datetime',
        'replaced_at' => 'datetime',
    ];

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }


    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
