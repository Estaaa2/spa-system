<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Free Trial Ends Soon</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f5; padding: 24px; margin: 0;">

    <div style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">

        <div style="background: linear-gradient(to right, #8B7355, #6F5430); padding: 24px; text-align: center;">
            <h1 style="color: #ffffff; font-size: 20px; margin: 0;">
                Levictas
            </h1>
        </div>

        <div style="padding: 24px;">
            <h2 style="font-size: 18px; color: #1f2937; margin-top: 0;">
                Your free trial ends in {{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }}
            </h2>

            <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">
                The free trial for <strong>{{ $spaName }}</strong> ends on
                <strong>{{ $endsAt?->format('F d, Y') }}</strong>.
            </p>

            <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">
                Choose a plan now to keep your spa running. Your paid plan starts when the trial ends,
                so you do not lose any trial days. If you do not choose a plan, your spa, staff accounts
                and public listing are paused until you do. Your data is kept.
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 16px; border-collapse: collapse;">
                @foreach ($plans as $plan)
                    <tr>
                        <td style="padding: 10px 0; border-top: 1px solid #e5e7eb; font-size: 14px; color: #1f2937;">
                            <strong>{{ $plan['name'] }}</strong><br>
                            <span style="font-size: 12px; color: #6b7280;">
                                {{ $plan['max_branches'] }} branches · {{ $plan['max_staff'] }} staff
                            </span>
                        </td>
                        <td style="padding: 10px 0; border-top: 1px solid #e5e7eb; font-size: 14px; color: #1f2937; text-align: right;">
                            ₱{{ number_format($plan['monthly_price'], 0) }}/month
                        </td>
                    </tr>
                @endforeach
            </table>

            <div style="text-align: center; margin-top: 28px;">
                <a
                    href="{{ route('owner.subscription.index') }}"
                    style="display: inline-block; background: linear-gradient(to right, #8B7355, #6F5430); color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-size: 14px; font-weight: 600;"
                >
                    Choose a Plan
                </a>
            </div>
        </div>
    </div>

</body>
</html>
