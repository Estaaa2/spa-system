<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One staff identity-verification submission (profile photo + government ID +
 * selfie holding the ID). Rows are history: a new submission makes a new row.
 *
 * File paths are on the private disk (config('identity.disk')) and are hidden
 * from serialization; they are only streamed by IdentityVerificationFileController.
 * All state changes go through App\Services\Identity\IdentityVerificationService.
 */
class IdentityVerification extends Model
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_REJECTED   = 'rejected';
    public const STATUS_WITHDRAWN  = 'withdrawn';
    public const STATUS_SUPERSEDED = 'superseded';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_WITHDRAWN,
        self::STATUS_SUPERSEDED,
    ];

    public const SUPERSEDED_PHOTO_CHANGED = 'photo_changed';
    public const SUPERSEDED_NAME_CHANGED  = 'name_changed';

    public const FILE_ID     = 'id';
    public const FILE_SELFIE = 'selfie';
    public const FILE_PHOTO  = 'photo';

    /** Everything is set explicitly by the service. */
    protected $guarded = ['id'];

    protected $hidden = [
        'id_image_path',
        'selfie_path',
        'photo_path_reviewed',
    ];

    protected $casts = [
        'consent_given_at'     => 'datetime',
        'consent_withdrawn_at' => 'datetime',
        'submitted_at'         => 'datetime',
        'reviewed_at'          => 'datetime',
        'files_purged_at'      => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function spa(): BelongsTo
    {
        return $this->belongsTo(Spa::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function fileViews(): HasMany
    {
        return $this->hasMany(IdentityFileView::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /** True while the ID or selfie file is still stored. */
    public function hasFiles(): bool
    {
        return $this->id_image_path !== null || $this->selfie_path !== null;
    }

    /** Private-disk path for an "id" or "selfie" request, or null. */
    public function pathFor(string $file): ?string
    {
        return match ($file) {
            self::FILE_ID     => $this->id_image_path,
            self::FILE_SELFIE => $this->selfie_path,
            default           => null,
        };
    }

    public function idTypeLabel(): string
    {
        return config("identity.id_types.{$this->id_type}", $this->id_type);
    }

    public static function idTypeKeys(): array
    {
        return array_keys(config('identity.id_types', []));
    }
}
