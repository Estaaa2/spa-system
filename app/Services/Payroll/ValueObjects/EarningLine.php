<?php

declare(strict_types=1);

namespace App\Services\Payroll\ValueObjects;

use App\Services\Payroll\Support\Decimal;

/**
 * One computed earning line — the in-memory shape of a payslip_lines row.
 * Money is a decimal string: quantity 2 dp, rate 4 dp, amount 2 dp (the
 * payslip_lines column scales). Negative quantity/amount only on monthly-paid
 * absence lines (BASIC).
 *
 * $date is NOT a payslip_lines column: it is the work/appointment date the line
 * belongs to, used for the per-day minimum-wage comparison and ordering. It is null
 * for lines that belong to no single day (semi-monthly basic, recurring items).
 */
final readonly class EarningLine
{
    public function __construct(
        public string $componentCode,
        public string $label,
        public string $kind,
        public ?int $branchId,
        public string $quantity,
        public string $rate,
        public string $amount,
        public ?string $sourceType,
        public ?int $sourceId,
        public ?string $note,
        public ?string $date,
    ) {
    }

    /**
     * amount = round2(round2(quantity) × round4(rate)), so a reader can re-check
     * every line from the two numbers printed beside it.
     */
    public static function priced(
        ComponentFlags $c,
        string $quantity,
        string $rate,
        ?int $branchId,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?string $date = null,
        ?string $labelSuffix = null,
    ): self {
        $q = Decimal::round2($quantity);
        $r = Decimal::round4($rate);

        return self::withAmount($c, $q, $r, Decimal::mul($q, $r), $branchId, $sourceType, $sourceId, $note, $date, $labelSuffix);
    }

    /** For lines whose amount is not quantity × rate (proration, caps, blended ND). */
    public static function withAmount(
        ComponentFlags $c,
        string $quantity,
        string $rate,
        string $amount,
        ?int $branchId,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $note = null,
        ?string $date = null,
        ?string $labelSuffix = null,
    ): self {
        $label = $labelSuffix === null ? $c->label : $c->label.' — '.$labelSuffix;

        return new self(
            componentCode: $c->code,
            label: mb_substr($label, 0, 100),
            kind: $c->kind,
            branchId: $branchId,
            quantity: Decimal::round2($quantity),
            rate: Decimal::round4($rate),
            amount: Decimal::round2($amount),
            sourceType: $sourceType,
            sourceId: $sourceId,
            note: $note,
            date: $date,
        );
    }

    /** Attributes for PayslipLine::create() (payslip_id / is_manual / created_by added by the caller). */
    public function toPayslipLineAttributes(): array
    {
        return [
            'component_code' => $this->componentCode,
            'label'          => $this->label,
            'kind'           => $this->kind,
            'branch_id'      => $this->branchId,
            'quantity'       => $this->quantity,
            'rate'           => $this->rate,
            'amount'         => $this->amount,
            'source_type'    => $this->sourceType,
            'source_id'      => $this->sourceId,
            'note'           => $this->note,
        ];
    }
}
