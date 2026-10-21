@php
    $planKey = \App\Models\Spa::normalisePlanKey($subscription->business_tier);
    $planKey = $planKey === 'expired' ? 'basic' : $planKey;
    $planConfig = config("plans.{$planKey}", []);
    $planName = $planConfig['name'] ?? ucfirst($planKey);

    // Built from config/plans.php so the email always matches the plan.
    $featureLabels = [
        'insights' => 'Decision support and reports',
        'all_branches' => 'Multi-branch management',
        'branch_public_listing' => 'Branch public listing on Levictas',
        'online_reservation' => 'Customer online reservations',
        'featured_listing' => 'Featured placement on Levictas',
        'inventory_full' => 'Full inventory, transfers and goods receiving',
        'manpower' => 'Hiring, deployment and staff management',
        'payroll' => 'Payroll and payslips',
        'finance' => 'Revenue, expenses and vendor bills',
        'procurement' => 'Suppliers and purchasing',
    ];

    $planFeatures = collect($planConfig['features'] ?? [])
        ->map(fn ($feature) => $featureLabels[$feature] ?? null)
        ->filter()
        ->values()
        ->prepend('Appointments, scheduling and services')
        ->push(sprintf(
            'Up to %d branches and %d staff members',
            $planConfig['max_branches'] ?? 0,
            $planConfig['max_staff'] ?? 0
        ))
        ->all();

    $startsLater = $subscription->starts_at?->isFuture() ?? false;

    $ownerName = $spa->owner?->name ?? 'Spa Owner';
    $paidDate = $subscription->updated_at?->format('F d, Y') ?? 'N/A';
    $validFrom = $subscription->starts_at?->format('F d, Y') ?? 'N/A';
    $validUntil = $subscription->expires_at?->format('F d, Y') ?? 'N/A';
    $referenceId = $subscription->paymongo_payment_id ?: ($subscription->paymongo_checkout_id ?: 'N/A');
    $credit = (float) ($subscription->credit_amount ?? 0);
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $planName }} Subscription Confirmed</title>
</head>

<body style="margin:0; padding:0; background-color:#F6EFE6; font-family: Georgia, 'Times New Roman', serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#F6EFE6; padding:40px 20px;">
    <tr>
        <td align="center">
            <table
                width="600"
                cellpadding="0"
                cellspacing="0"
                style="background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.08);"
            >

                {{-- Header --}}
                <tr>
                    <td style="background:linear-gradient(135deg, #6F5430 0%, #8B7355 100%); padding:36px 40px; text-align:center;">
                        <p style="margin:0 0 6px 0; font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:rgba(255,255,255,0.75);">
                            Levictas · Spa &amp; Wellness
                        </p>

                        <h1 style="margin:0; font-size:26px; font-weight:600; color:#ffffff; letter-spacing:0.02em;">
                            {{ $spa->name }}
                        </h1>

                        <div style="margin-top:20px;">
                            <span style="background:rgba(255,255,255,0.15); border-radius:50px; padding:8px 22px; font-size:13px; color:#ffffff; font-weight:600; letter-spacing:0.05em;">
                                ✦ {{ strtoupper($planName) }} PLAN {{ $startsLater ? 'CONFIRMED' : 'ACTIVATED' }} ✦
                            </span>
                        </div>
                    </td>
                </tr>

                {{-- Greeting --}}
                <tr>
                    <td style="padding:36px 40px 0 40px;">
                        <p style="margin:0 0 12px 0; font-size:16px; color:#3C2F23;">
                            Hello <strong>{{ $ownerName }}</strong>,
                        </p>

                        <p style="margin:0; font-size:15px; color:#555555; line-height:1.7;">
                            Your subscription payment has been received.
                            @if ($startsLater)
                                Your spa's <strong style="color:#6F5430;">{{ $planName }} plan</strong>
                                starts on <strong>{{ $validFrom }}</strong>, right after your current access ends.
                            @else
                                Your spa's <strong style="color:#6F5430;">{{ $planName }} plan</strong>
                                is now active on the Levictas platform.
                            @endif
                        </p>
                    </td>
                </tr>

                {{-- Receipt --}}
                <tr>
                    <td style="padding:28px 40px;">
                        <table
                            width="100%"
                            cellpadding="0"
                            cellspacing="0"
                            style="background-color:#F6EFE6; border-radius:10px; border:1px solid #E8DDD0; overflow:hidden;"
                        >
                            <tr>
                                <td style="padding:16px 20px; border-bottom:1px solid #E8DDD0;">
                                    <p style="margin:0; font-size:11px; letter-spacing:0.15em; text-transform:uppercase; color:#8B7355; font-weight:600;">
                                        Payment Receipt
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:20px;">
                                    <table width="100%" cellpadding="0" cellspacing="0">

                                        <tr>
                                            <td style="padding:7px 0; font-size:14px; color:#777777; border-bottom:1px solid #EDE6DD;">
                                                Plan
                                            </td>

                                            <td style="padding:7px 0; font-size:14px; color:#3C2F23; font-weight:600; text-align:right; border-bottom:1px solid #EDE6DD;">
                                                {{ $planName }} Plan
                                            </td>
                                        </tr>

                                        <tr>
                                            <td style="padding:7px 0; font-size:14px; color:#777777; border-bottom:1px solid #EDE6DD;">
                                                Amount Paid
                                            </td>

                                            <td style="padding:7px 0; font-size:14px; color:#3C2F23; font-weight:600; text-align:right; border-bottom:1px solid #EDE6DD;">
                                                ₱{{ number_format((float) $subscription->amount, 2) }}
                                            </td>
                                        </tr>

                                        @if ($credit > 0)
                                            <tr>
                                                <td style="padding:7px 0; font-size:14px; color:#777777; border-bottom:1px solid #EDE6DD;">
                                                    Unused Basic Credit
                                                </td>

                                                <td style="padding:7px 0; font-size:14px; color:#3C2F23; font-weight:600; text-align:right; border-bottom:1px solid #EDE6DD;">
                                                    −₱{{ number_format($credit, 2) }}
                                                </td>
                                            </tr>
                                        @endif

                                        <tr>
                                            <td style="padding:7px 0; font-size:14px; color:#777777; border-bottom:1px solid #EDE6DD;">
                                                Date Paid
                                            </td>

                                            <td style="padding:7px 0; font-size:14px; color:#3C2F23; font-weight:600; text-align:right; border-bottom:1px solid #EDE6DD;">
                                                {{ $paidDate }}
                                            </td>
                                        </tr>

                                        <tr>
                                            <td style="padding:7px 0; font-size:14px; color:#777777; border-bottom:1px solid #EDE6DD;">
                                                Valid From
                                            </td>

                                            <td style="padding:7px 0; font-size:14px; color:#3C2F23; font-weight:600; text-align:right; border-bottom:1px solid #EDE6DD;">
                                                {{ $validFrom }}
                                            </td>
                                        </tr>

                                        <tr>
                                            <td style="padding:7px 0; font-size:14px; color:#777777; border-bottom:1px solid #EDE6DD;">
                                                Valid Until
                                            </td>

                                            <td style="padding:7px 0; font-size:14px; color:#3C2F23; font-weight:600; text-align:right; border-bottom:1px solid #EDE6DD;">
                                                {{ $validUntil }}
                                            </td>
                                        </tr>

                                        <tr>
                                            <td style="padding:7px 0; font-size:14px; color:#777777;">
                                                Reference ID
                                            </td>

                                            <td style="padding:7px 0; font-size:12px; color:#8B7355; font-weight:600; text-align:right; word-break:break-all;">
                                                {{ $referenceId }}
                                            </td>
                                        </tr>

                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Features --}}
                <tr>
                    <td style="padding:0 40px 28px 40px;">
                        <p style="margin:0 0 14px 0; font-size:11px; letter-spacing:0.15em; text-transform:uppercase; color:#8B7355; font-weight:600;">
                            What's now unlocked
                        </p>

                        <table width="100%" cellpadding="0" cellspacing="0">
                            @foreach ($planFeatures as $feature)
                                <tr>
                                    <td style="padding:5px 0;">
                                        <table cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="width:22px; vertical-align:middle;">
                                                    <div style="width:18px; height:18px; background-color:#8B7355; border-radius:50%; text-align:center; line-height:18px;">
                                                        <span style="color:#ffffff; font-size:10px; font-weight:bold;">
                                                            ✓
                                                        </span>
                                                    </div>
                                                </td>

                                                <td style="font-size:14px; color:#555555; padding-left:8px; vertical-align:middle;">
                                                    {{ $feature }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>

                {{-- Divider --}}
                <tr>
                    <td style="padding:0 40px;">
                        <hr style="border:none; border-top:1px solid #E8DDD0; margin:0;">
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:24px 40px; text-align:center;">
                        <p style="margin:0 0 6px 0; font-size:13px; color:#8B7355; font-weight:600; letter-spacing:0.08em;">
                            LEVICTAS · SPA &amp; WELLNESS SANCTUARY
                        </p>

                        <p style="margin:0; font-size:12px; color:#aaaaaa;">
                            This is an automated payment confirmation. Please keep this for your records.
                        </p>

                        <p style="margin:8px 0 0 0; font-size:12px; color:#cccccc;">
                            © {{ date('Y') }} Levictas. All rights reserved.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
