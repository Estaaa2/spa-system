{{--
    A refund was sent (customer cancellation #15). Same look as
    emails/booking-therapist.blade.php.
--}}
@php($peso = fn ($v) => '₱' . number_format((float) $v, 2))
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your refund has been sent</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f5; padding: 24px; margin: 0;">

    <div style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">

        <div style="background: linear-gradient(to right, #8B7355, #6F5430); padding: 24px; text-align: center;">
            <h1 style="color: #ffffff; font-size: 20px; margin: 0;">Levictas</h1>
        </div>

        <div style="padding: 24px;">
            <h2 style="font-size: 18px; color: #1f2937; margin-top: 0;">Your refund has been sent</h2>

            <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">Hi {{ $customerName }},</p>

            <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">
                We sent your refund of <strong>{{ $peso($amount) }}</strong>{{ $sentOn ? ' on ' . $sentOn : '' }}.
            </p>

            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px;">
                <tr>
                    <td style="padding: 6px 0; color: #8B7355; font-weight: bold; width: 110px; vertical-align: top;">Sent by</td>
                    <td style="padding: 6px 0; color: #3C2F23;">{{ $method }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #8B7355; font-weight: bold; vertical-align: top;">Reference</td>
                    <td style="padding: 6px 0; color: #3C2F23; word-break: break-all;">{{ $reference }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #8B7355; font-weight: bold; vertical-align: top;">Booking</td>
                    <td style="padding: 6px 0; color: #3C2F23;">
                        {{ $treatment }} · {{ $spaName }}{{ $branchName ? ' — ' . $branchName : '' }}
                        @if ($date)<br>{{ $date }}@endif
                    </td>
                </tr>
            </table>

            <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">
                How long it takes to reach you depends on your payment method and bank.
                If it hasn't arrived after a few days, email {{ \App\Support\Legal\Contact::email() }} with the reference above.
            </p>

            <div style="text-align: center; margin-top: 28px;">
                <a href="{{ $url }}"
                   style="display: inline-block; background: linear-gradient(to right, #8B7355, #6F5430); color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-size: 14px; font-weight: 600;">
                    View My Appointments
                </a>
            </div>

            <p style="margin-top: 24px; color: #999; font-size: 12px;">This is an automated message from Levictas.</p>
        </div>
    </div>

</body>
</html>
