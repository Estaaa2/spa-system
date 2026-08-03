<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f5; padding: 24px; margin: 0;">

    <div style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">

        <div style="background: linear-gradient(to right, #8B7355, #6F5430); padding: 24px; text-align: center;">
            <h1 style="color: #ffffff; font-size: 20px; margin: 0;">Levictas</h1>
        </div>

        <div style="padding: 24px;">
            <h2 style="font-size: 18px; color: #1f2937; margin-top: 0;">
                A branch deployment needs your response
            </h2>

            <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">
                Hi {{ $staffName }}, HR has submitted a request to deploy you from
                <strong>{{ $fromBranch }}</strong> to <strong>{{ $toBranch }}</strong>,
                starting <strong>{{ $startDate }}</strong>.
            </p>

            <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">
                @if($isPermanent)
                    This would be a <strong>permanent</strong> transfer.
                @elseif($endDate)
                    This deployment would run until <strong>{{ $endDate }}</strong>.
                @else
                    No end date has been set yet &mdash; you'd stay at the new branch until recalled.
                @endif
            </p>

            <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">
                This request also needs Owner approval separately, but your response matters too &mdash;
                please log in to your dashboard to accept or decline.
            </p>

            <div style="text-align: center; margin-top: 28px;">
                <a href="{{ route('dashboard') }}"
                   style="display: inline-block; background: linear-gradient(to right, #8B7355, #6F5430); color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-size: 14px; font-weight: 600;">
                    Review Request
                </a>
            </div>
        </div>

    </div>

</body>
</html>
