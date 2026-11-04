<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscription Expiring Soon</title>
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
                Your subscription is expiring soon
            </h2>

            <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">
                Hi, this is a reminder that your
                <strong>{{ $planName }}</strong>
                subscription for
                <strong>{{ $spaName }}</strong>
                will expire on
                <strong>
                    {{ $expiresAt?->format('F d, Y') ?? 'the upcoming expiry date' }}
                </strong>.
            </p>

            <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">
                Please renew before the expiry date to keep your current plan and its available features active.
            </p>

            <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">
                If the subscription expires, access will be determined by your account's current subscription status and plan eligibility.
            </p>

            <div style="text-align: center; margin-top: 28px;">
                <a
                    href="{{ route('owner.subscription.index') }}"
                    style="display: inline-block; background: linear-gradient(to right, #8B7355, #6F5430); color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-size: 14px; font-weight: 600;"
                >
                    Manage Subscription
                </a>
            </div>
        </div>
    </div>

</body>
</html>
