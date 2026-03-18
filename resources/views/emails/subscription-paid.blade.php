<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Confirmed</title>
</head>
<body style="margin:0; padding:0; background-color:#F6EFE6; font-family: Georgia, 'Times New Roman', serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#F6EFE6; padding: 40px 20px;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0"
                style="background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">

                {{-- HEADER --}}
                <tr>
                    <td style="background: linear-gradient(135deg, #6F5430 0%, #8B7355 100%); padding: 36px 40px; text-align:center;">
                        <p style="margin:0 0 6px 0; font-size:11px; letter-spacing:0.2em; text-transform:uppercase; color:rgba(255,255,255,0.75);">
                            Levictas · Spa & Wellness
                        </p>
                        <h1 style="margin:0; font-size:26px; font-weight:600; color:#ffffff; letter-spacing:0.02em;">
                            {{ $spa->name }}
                        </h1>
                        <div style="margin-top:20px;">
                            <span style="background:rgba(255,255,255,0.15); border-radius:50px; padding: 8px 22px; font-size:13px; color:#ffffff; font-weight:600; letter-spacing:0.05em;">
                                ✦ PROFESSIONAL PLAN ACTIVATED ✦
                            </span>
                        </div>
                    </td>
                </tr>

                {{-- GREETING --}}
                <tr>
                    <td style="padding: 36px 40px 0 40px;">
                        <p style="margin:0 0 12px 0; font-size:16px; color:#3C2F23;">
                            Hello <strong>{{ $spa->owner->name }}</strong>,
                        </p>
                        <p style="margin:0; font-size:15px; color:#555555; line-height:1.7;">
                            Your subscription payment has been received and your spa is now listed as a
                            <strong style="color:#6F5430;">Professional Partner</strong> on the Levictas platform.
                            Customers can now discover and book appointments with your spa.
                        </p>
                    </td>
                </tr>

                {{-- RECEIPT BOX --}}
                <tr>
                    <td style="padding: 28px 40px;">
                        <table width="100%" cellpadding="0" cellspacing="0"
                            style="background-color:#F6EFE6; border-radius:10px; border: 1px solid #E8DDD0; overflow:hidden;">

                            <tr>
                                <td style="padding: 16px 20px; border-bottom: 1px solid #E8DDD0;">
                                    <p style="margin:0; font-size:11px; letter-spacing:0.15em; text-transform:uppercase; color:#8B7355; font-weight:600;">
                                        Payment Receipt
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <td style="padding: 20px;">
                                    <table width="100%" cellpadding="0" cellspacing="0">

                                        <tr>
                                            <td style="padding: 7px 0; font-size:14px; color:#777777; border-bottom:1px solid #EDE6DD;">
                                                Plan
                                            </td>
                                            <td style="padding: 7px 0; font-size:14px; color:#3C2F23; font-weight:600; text-align:right; border-bottom:1px solid #EDE6DD;">
                                                Professional Tier
                                            </td>
                                        </tr>

                                        <tr>
                                            <td style="padding: 7px 0; font-size:14px; color:#777777; border-bottom:1px solid #EDE6DD;">
                                                Amount Paid
                                            </td>
                                            <td style="padding: 7px 0; font-size:14px; color:#3C2F23; font-weight:600; text-align:right; border-bottom:1px solid #EDE6DD;">
                                                ₱{{ number_format($subscription->amount, 2) }}
                                            </td>
                                        </tr>

                                        <tr>
                                            <td style="padding: 7px 0; font-size:14px; color:#777777; border-bottom:1px solid #EDE6DD;">
                                                Date Paid
                                            </td>
                                            <td style="padding: 7px 0; font-size:14px; color:#3C2F23; font-weight:600; text-align:right; border-bottom:1px solid #EDE6DD;">
                                                {{ $subscription->starts_at->format('F d, Y') }}
                                            </td>
                                        </tr>

                                        <tr>
                                            <td style="padding: 7px 0; font-size:14px; color:#777777; border-bottom:1px solid #EDE6DD;">
                                                Valid Until
                                            </td>
                                            <td style="padding: 7px 0; font-size:14px; color:#3C2F23; font-weight:600; text-align:right; border-bottom:1px solid #EDE6DD;">
                                                {{ $subscription->expires_at->format('F d, Y') }}
                                            </td>
                                        </tr>

                                        <tr>
                                            <td style="padding: 7px 0; font-size:14px; color:#777777;">
                                                Reference ID
                                            </td>
                                            <td style="padding: 7px 0; font-size:12px; color:#8B7355; font-weight:600; text-align:right; word-break:break-all;">
                                                {{ $subscription->paymongo_checkout_id }}
                                            </td>
                                        </tr>

                                    </table>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>

                {{-- FEATURES UNLOCKED --}}
                <tr>
                    <td style="padding: 0 40px 28px 40px;">
                        <p style="margin:0 0 14px 0; font-size:11px; letter-spacing:0.15em; text-transform:uppercase; color:#8B7355; font-weight:600;">
                            What's now unlocked
                        </p>

                        <table width="100%" cellpadding="0" cellspacing="0">
                            @foreach([
                                'Branch public listing on Levictas',
                                'Customer online reservations',
                                'Enhanced decision support tools',
                                'Priority support',
                                'Unlimited staff and branches',
                            ] as $feature)
                            <tr>
                                <td style="padding: 5px 0;">
                                    <table cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="width:22px; vertical-align:middle;">
                                                <div style="width:18px; height:18px; background-color:#8B7355; border-radius:50%; text-align:center; line-height:18px;">
                                                    <span style="color:#ffffff; font-size:10px; font-weight:bold;">✓</span>
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

                {{-- DIVIDER --}}
                <tr>
                    <td style="padding: 0 40px;">
                        <hr style="border:none; border-top:1px solid #E8DDD0; margin:0;">
                    </td>
                </tr>

                {{-- FOOTER --}}
                <tr>
                    <td style="padding: 24px 40px; text-align:center;">
                        <p style="margin:0 0 6px 0; font-size:13px; color:#8B7355; font-weight:600; letter-spacing:0.08em;">
                            LEVICTAS · SPA & WELLNESS SANCTUARY
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
