<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SpaVerificationDocument extends Model
{
    protected $fillable = [
        'spa_id',
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
    ];

    protected $casts = [
        'owner_expiry_date' => 'date',
        'ocr_expiry_date' => 'date',
        'expiry_date' => 'date',
        'expiry_scanned_at' => 'datetime',
        'expiry_verified_at' => 'datetime',
    ];

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }

    public function expiryVerifier()
    {
        return $this->belongsTo(
            User::class,
            'expiry_verified_by'
        );
    }

    public function renewalOpensAt(int $daysBefore = 30): ?Carbon
    {
        if (!$this->expiry_date) {
            return null;
        }

        return $this->expiry_date
            ->copy()
            ->startOfDay()
            ->subDays($daysBefore);
    }

    public function isRenewalDue(int $daysBefore = 30): bool
    {
        $renewalOpensAt =
            $this->renewalOpensAt(
                $daysBefore
            );

        if (!$renewalOpensAt) {
            return false;
        }

        return today()
            ->startOfDay()
            ->gte(
                $renewalOpensAt
            );
    }
}
