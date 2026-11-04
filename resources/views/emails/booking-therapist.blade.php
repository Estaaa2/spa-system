{{--
    Booking confirmation / therapist assigned / therapist changed.
    Same look as emails/verification-review-result.blade.php (warm customer palette).
    The therapist photo is embedded (cid:), never linked. $message is only set when
    the mail is really sent; a preview render shows initials instead.
--}}
@php
    $heading = match ($kind) {
        'assigned' => 'Your therapist has been assigned',
        'changed'  => 'Your therapist has changed',
        default    => 'Your booking is confirmed',
    };
    $photoSrc = ($photoBytes && isset($message)) ? $message->embedData($photoBytes, 'therapist.jpg', 'image/jpeg') : null;
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
                @if ($kind === 'changed')
                    The therapist for your appointment has changed. Please check the new photo below.
                @elseif ($kind === 'assigned')
                    A therapist has been assigned to your appointment.
                @else
                    Thank you for booking with <strong>{{ $spaName }}</strong>. Here are your appointment details.
                @endif
            </p>

            {{-- Appointment --}}
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
                    <td style="padding: 6px 0; color: #8B7355; font-weight: bold; vertical-align: top;">When</td>
                    <td style="padding: 6px 0; color: #3C2F23;">{{ $date }} at {{ $time }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #8B7355; font-weight: bold; vertical-align: top;">Where</td>
                    <td style="padding: 6px 0; color: #3C2F23;">{{ $isHome ? 'Home service: ' . $address : 'At the branch' }}</td>
                </tr>
            </table>

            {{-- Therapist card --}}
            <div style="border: 1px solid #eadfce; background: #F6EFE6; border-radius: 12px; padding: 16px; margin-top: 8px;">
                <p style="margin: 0 0 10px; color: #8B7355; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: .05em;">Your therapist</p>

                @if ($therapistName)
                    <table role="presentation" style="border-collapse: collapse;">
                        <tr>
                            <td style="padding-right: 14px; vertical-align: middle;">
                                @if ($photoSrc)
                                    <img src="{{ $photoSrc }}" alt="Photo of {{ $therapistName }}" width="96" height="96"
                                         style="display: block; width: 96px; height: 96px; border-radius: 48px; object-fit: cover;">
                                @else
                                    <div style="width: 96px; height: 96px; border-radius: 48px; background: #8B7355; color: #ffffff; font-size: 32px; font-weight: bold; line-height: 96px; text-align: center;">{{ $initials }}</div>
                                @endif
                            </td>
                            <td style="vertical-align: middle;">
                                <p style="margin: 0; font-size: 16px; font-weight: bold; color: #3C2F23;">{{ $therapistName }}</p>
                                @if ($verified)
                                    <p style="margin: 6px 0 0; display: inline-block; background: #d1fae5; color: #065f46; font-size: 12px; font-weight: bold; padding: 3px 10px; border-radius: 999px;">&#10003; Identity verified</p>
                                @endif
                            </td>
                        </tr>
                    </table>
                @else
                    <p style="margin: 0; color: #4b5563; font-size: 14px; line-height: 1.6;">
                        Your therapist will be shown once the branch assigns one. We'll email you their photo when they do.
                    </p>
                @endif
            </div>

            {{-- Home service: check the person, then give the code --}}
            @if ($isHome)
                <div style="border: 1px solid #fde68a; background: #fffbeb; border-radius: 12px; padding: 16px; margin-top: 16px;">
                    <p style="margin: 0; color: #92400e; font-size: 14px; line-height: 1.6;">
                        <strong>When your therapist arrives:</strong> check that they are the person in the photo above.
                        If they are not, do not let them in{{ $branchPhone ? ', and call the branch at ' . $branchPhone : ', and call the branch' }}.
                    </p>

                    @if ($arrivalCode)
                        <p style="margin: 14px 0 4px; color: #92400e; font-size: 13px;">If they match, tell them this arrival code:</p>
                        <p style="margin: 0; font-size: 32px; font-weight: bold; letter-spacing: 10px; color: #3C2F23; font-family: 'Courier New', monospace;">{{ $arrivalCode }}</p>
                        <p style="margin: 8px 0 0; color: #92400e; font-size: 12px; line-height: 1.5;">
                            Only you have this code; the therapist does not. Never send it in a message before they arrive.
                        </p>
                    @endif
                </div>
            @endif

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
