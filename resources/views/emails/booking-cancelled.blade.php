{{--
    Booking cancelled by the customer or by the spa (customer cancellation #15).
    Same look as emails/booking-therapist.blade.php.
--}}
@php
    $heading = $by === 'staff' ? 'The spa cancelled your booking' : 'Your booking is cancelled';
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f5; padding: 24px; margin: 0;">

    <div style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">

        <div style="background: linear-gradient(to right, #8B7355, #6F5430); padding: 24px; text-align: center;">
            <h1 style="color: #ffffff; font-size: 20px; margin: 0;">Levictas</h1>
        </div>

        <div style="padding: 24px;">
            <h2 style="font-size: 18px; color: #1f2937; margin-top: 0;">{{ $heading }}</h2>

            <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">Hi {{ $customerName }},</p>

            <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">
                @if ($by === 'staff')
                    <strong>{{ $spaName }}</strong> cancelled your appointment below. We're sorry for the trouble.
                    You get back everything you paid online.
                @else
                    You cancelled your appointment below.
                @endif
            </p>

            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px;">
                <tr>
                    <td style="padding: 6px 0; color: #8B7355; font-weight: bold; width: 110px; vertical-align: top;">Spa</td>
                    <td style="padding: 6px 0; color: #3C2F23;">{{ $spaName }}{{ $branchName ? ' — ' . $branchName : '' }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #8B7355; font-weight: bold; vertical-align: top;">Treatment</td>
                    <td style="padding: 6px 0; color: #3C2F23;">{{ $treatment }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #8B7355; font-weight: bold; vertical-align: top;">Was on</td>
                    <td style="padding: 6px 0; color: #3C2F23;">{{ $date }} at {{ $time }}</td>
                </tr>
            </table>

            <div style="border: 1px solid #eadfce; background: #F6EFE6; border-radius: 12px; padding: 16px;">
                <table role="presentation" style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <tr>
                        <td style="padding: 4px 0; color: #4b5563;">You paid</td>
                        <td style="padding: 4px 0; color: #3C2F23; text-align: right;">{{ $peso($paid) }}</td>
                    </tr>
                    @if ($kept > 0)
                        <tr>
                            <td style="padding: 4px 0; color: #4b5563;">Reservation fee kept</td>
                            <td style="padding: 4px 0; color: #3C2F23; text-align: right;">{{ $peso($kept) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 8px 0 4px; color: #3C2F23; font-weight: bold; border-top: 1px solid #eadfce;">Refund</td>
                        <td style="padding: 8px 0 4px; color: #6F5430; font-weight: bold; text-align: right; border-top: 1px solid #eadfce;">{{ $peso($refund) }}</td>
                    </tr>
                </table>
            </div>

            <p style="color: #4b5563; font-size: 14px; line-height: 1.6;">
                @if ($refund > 0)
                    Levictas will send your refund back to you, usually to the e-wallet you paid with.
                    We'll email you again when it's sent, and you can follow it in My Appointments.
                @else
                    The reservation fee is kept, as in our Terms, so there is nothing to refund.
                @endif
            </p>

            <div style="text-align: center; margin-top: 28px;">
                <a href="{{ $url }}"
                   style="display: inline-block; background: linear-gradient(to right, #8B7355, #6F5430); color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-size: 14px; font-weight: 600;">
                    View My Appointments
                </a>
            </div>

            <p style="margin-top: 24px; color: #999; font-size: 12px;">
                Questions? Call the branch{{ $branchPhone ? ' at ' . $branchPhone : '' }}.
                This is an automated message from Levictas.
            </p>
        </div>
    </div>

</body>
</html>
