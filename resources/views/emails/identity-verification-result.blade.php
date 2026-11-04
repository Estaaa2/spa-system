<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $approved ? 'Your identity is verified' : 'Your identity verification was not approved' }}</title>
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
                {{ $approved ? 'Your identity is verified' : 'Your identity verification was not approved' }}
            </h2>

            <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">
                Hi {{ $firstName }},
            </p>

            @if ($approved)
                <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">
                    A Levictas admin checked your profile photo, ID and selfie, and approved them.
                    You can now be assigned online home-service bookings. Customers who book home service with you
                    will see your profile photo, your name and a verified badge, so they can recognise you when you arrive.
                </p>
            @else
                <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">
                    A Levictas admin checked your profile photo, ID and selfie, but could not approve them yet.
                    You can fix the issue below and submit again from your profile page.
                </p>

                @if ($reason)
                    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; margin-top: 16px;">
                        <p style="color: #991b1b; font-size: 14px; line-height: 1.6; margin: 0;">
                            <strong>Reason:</strong>
                            {{ $reason }}
                        </p>
                    </div>
                @endif
            @endif

            <p style="color: #6b7280; font-size: 13px; line-height: 1.6; margin-top: 16px;">
                Your ID and selfie have been deleted from our system.
                @if ($approved)
                    If you change your profile photo, you'll need to verify again.
                @endif
            </p>

            <div style="text-align: center; margin-top: 28px;">
                <a
                    href="{{ $url }}"
                    style="display: inline-block; background: linear-gradient(to right, #8B7355, #6F5430); color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-size: 14px; font-weight: 600;"
                >
                    {{ $approved ? 'Open My Profile' : 'Submit Again' }}
                </a>
            </div>
        </div>
    </div>

</body>
</html>
