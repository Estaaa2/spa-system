<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">

    <style>
        body {
            font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif;
            color: #2D3748;
            font-size: 13px;
            margin: 0;
            padding: 40px;
        }

        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #8B7355;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header .brand {
            display: table-cell;
            vertical-align: middle;
        }

        .brand-name {
            font-size: 22px;
            font-weight: bold;
            color: #8B7355;
        }

        .brand-tagline {
            font-size: 10px;
            letter-spacing: 2px;
            color: #718096;
        }

        .header .status {
            display: table-cell;
            text-align: right;
            vertical-align: middle;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            background-color: #DCFCE7;
            color: #15803D;
            font-weight: bold;
            font-size: 12px;
        }

        h1 {
            font-size: 18px;
            margin-bottom: 4px;
            color: #1A202C;
        }

        .subtext {
            color: #718096;
            margin-bottom: 30px;
        }

        table.details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        table.details td {
            padding: 10px 0;
            border-bottom: 1px solid #E2E8F0;
        }

        table.details td.label {
            color: #718096;
            width: 40%;
        }

        table.details td.value {
            font-weight: bold;
            text-align: right;
        }

        .credit {
            color: #15803D;
        }

        .total-row td {
            border-top: 2px solid #2D3748;
            border-bottom: none;
            padding-top: 16px;
            font-size: 16px;
        }

        .total-row td.value {
            color: #15803D;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 11px;
            color: #A0AEC0;
        }
    </style>
</head>

<body>
    @php
        $planKey = \App\Models\Spa::normalisePlanKey($subscription->business_tier);
        $planName = config("plans.{$planKey}.name", ucfirst($planKey));
        $billingCycle = ucfirst($subscription->billing_cycle ?? 'monthly');
        $credit = (float) $subscription->credit_amount;
        $listPrice = (float) $subscription->amount + $credit;
        $paymentId = $subscription->paymongo_payment_id
            ?? $subscription->paymongo_checkout_id
            ?? '—';
        $kindLabel = [
            'new' => 'New subscription',
            'renewal' => 'Renewal',
            'upgrade' => 'Upgrade',
            'downgrade' => 'Plan change',
        ][$subscription->kind] ?? 'Subscription';
        $ownerName = trim(($spa->owner->first_name ?? '') . ' ' . ($spa->owner->last_name ?? ''));
    @endphp

    {{-- Levictas issues the receipt; the spa is the customer. --}}
    <div class="header">
        <div class="brand">
            <div class="brand-name">Levictas</div>
            <div class="brand-tagline">SPA MANAGEMENT PLATFORM</div>
        </div>

        <div class="status">
            <span class="status-badge">PAID</span>
        </div>
    </div>

    <h1>Subscription Payment Receipt</h1>
    @unless ($subscription->livemode)
        <p style="margin: 4px 0 0; color: #1d4ed8; font-weight: bold;">TEST MODE: no real money was charged.</p>
    @endunless

    <p class="subtext">
        {{ $planName }} Plan · {{ $billingCycle }} Billing · {{ $kindLabel }}
    </p>

    <table class="details">
        <tr>
            <td class="label">Billed To</td>
            <td class="value">
                {{ $spa->name }}
                <br>
                <span style="font-weight: normal; color: #718096;">
                    {{ $ownerName }}{{ $ownerName && ($spa->owner->email ?? '') ? ' · ' : '' }}{{ $spa->owner->email ?? '' }}
                </span>
            </td>
        </tr>

        <tr>
            <td class="label">Plan</td>
            <td class="value">{{ $planName }} ({{ $billingCycle }})</td>
        </tr>

        <tr>
            <td class="label">Service Period</td>
            <td class="value">
                {{ $subscription->starts_at?->format('F d, Y') ?? '—' }}
                –
                {{ $subscription->expires_at?->format('F d, Y') ?? '—' }}
            </td>
        </tr>

        <tr>
            <td class="label">Payment Date</td>
            <td class="value">
                {{ $subscription->updated_at?->format('F d, Y h:i A') ?? '—' }}
            </td>
        </tr>

        <tr>
            <td class="label">Payment Method</td>
            <td class="value">{{ $subscription->paymentMethodLabel() }}</td>
        </tr>

        <tr>
            <td class="label">Payment ID</td>
            <td class="value">{{ $paymentId }}</td>
        </tr>

        <tr>
            <td class="label">Plan Price</td>
            <td class="value">₱ {{ number_format($listPrice, 2) }}</td>
        </tr>

        @if ($credit > 0)
            <tr>
                <td class="label">Unused Basic Credit</td>
                <td class="value credit">− ₱ {{ number_format($credit, 2) }}</td>
            </tr>
        @endif

        <tr class="total-row">
            <td class="label">Total Paid</td>
            <td class="value">
                ₱ {{ number_format((float) $subscription->amount, 2) }}
            </td>
        </tr>
    </table>

    <div class="footer">
        Payment processed by PayMongo. This receipt confirms your Levictas subscription payment.
        <br>
        Generated by Levictas · {{ now()->format('F d, Y') }}
    </div>
</body>
</html>
