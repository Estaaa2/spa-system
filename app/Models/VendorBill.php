<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class VendorBill extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_MATCH = 'pending_match';
    public const STATUS_MATCHED = 'matched';
    public const STATUS_DISCREPANCY = 'discrepancy';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'spa_id',
        'branch_id',
        'supplier_id',
        'purchase_order_id',
        'created_by',
        'approved_by',
        'invoice_number',
        'invoice_date',
        'due_date',
        'subtotal',
        'total_amount',
        'status',
        'match_details',
        'matched_at',
        'approved_at',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'match_details' => 'array',
        'matched_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function spa()
    {
        return $this->belongsTo(Spa::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(VendorBillItem::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (VendorBill $vendorBill) {
            $originalStatus = $vendorBill->getOriginal('status');
            $nextStatus = $vendorBill->status;

            if ($vendorBill->isDirty('status')) {
                if ($nextStatus === self::STATUS_APPROVED) {
                    $matchDetails = $vendorBill->match_details;

                    $matched =
                        is_array($matchDetails) &&
                        ($matchDetails['matched'] ?? false) === true;

                    $discrepancyLines =
                        is_array($matchDetails)
                            ? (int) ($matchDetails['summary']['discrepancy_lines'] ?? 0)
                            : 0;

                    if (
                        $originalStatus !== self::STATUS_MATCHED ||
                        !$matched ||
                        $discrepancyLines !== 0 ||
                        !$vendorBill->matched_at
                    ) {
                        throw ValidationException::withMessages([
                            'status' =>
                                'Only a successfully matched vendor bill with no unresolved discrepancies can be approved.',
                        ]);
                    }

                    if (
                        !$vendorBill->approved_by ||
                        !$vendorBill->approved_at
                    ) {
                        throw ValidationException::withMessages([
                            'approval' =>
                                'The approving user and approval timestamp are required.',
                        ]);
                    }
                }

                if ($nextStatus === self::STATUS_PAID) {
                    if (
                        $originalStatus !== self::STATUS_APPROVED ||
                        !$vendorBill->approved_by ||
                        !$vendorBill->approved_at
                    ) {
                        throw ValidationException::withMessages([
                            'status' =>
                                'Only an approved vendor bill can be marked as paid.',
                        ]);
                    }

                    if (!$vendorBill->paid_at) {
                        throw ValidationException::withMessages([
                            'payment' =>
                                'A payment timestamp is required before the vendor bill can be marked as paid.',
                        ]);
                    }
                }
            }

            $protectedFinancialFields = [
                'spa_id',
                'branch_id',
                'supplier_id',
                'purchase_order_id',
                'invoice_number',
                'invoice_date',
                'due_date',
                'subtotal',
                'total_amount',
            ];

            if (in_array($originalStatus, [
                self::STATUS_MATCHED,
                self::STATUS_APPROVED,
                self::STATUS_PAID,
                self::STATUS_CANCELLED,
            ], true)) {
                foreach ($protectedFinancialFields as $field) {
                    if ($vendorBill->isDirty($field)) {
                        throw ValidationException::withMessages([
                            'vendor_bill' =>
                                'Financial details can no longer be changed after a vendor bill has been matched.',
                        ]);
                    }
                }
            }
        });
    }

}
