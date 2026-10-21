<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One admin extension of a spa's free trial. Kept as an audit record:
 * who extended it, by how much, from when to when, and why.
 */
class TrialExtension extends Model
{
    protected $fillable = [
        'spa_id',
        'admin_id',
        'days',
        'previous_ends_at',
        'new_ends_at',
        'reason',
    ];

    protected $casts = [
        'days' => 'integer',
        'previous_ends_at' => 'datetime',
        'new_ends_at' => 'datetime',
    ];

    public function spa(): BelongsTo
    {
        return $this->belongsTo(Spa::class)->withTrashed();
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
