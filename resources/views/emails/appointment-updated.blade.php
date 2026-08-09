@php
    $oldDateFormatted  = \Carbon\Carbon::parse($oldDate)->format('F d, Y');
    $oldTimeFormatted  = \Carbon\Carbon::parse($oldStartTime)->format('h:i A');
    $newDateFormatted  = $booking->appointment_date->format('F d, Y');
    $newTimeFormatted  = \Carbon\Carbon::parse($booking->start_time)->format('h:i A');
@endphp

<div style="font-family: Arial, sans-serif; max-width: 560px; margin: 0 auto; color: #3C2F23;">
    <h2 style="color: #6F5430;">Your Appointment Has Been Rescheduled</h2>

    <p>Hi {{ $booking->customer_name }},</p>

    <p>Your appointment at <strong>{{ $booking->spa->name ?? 'the spa' }}</strong> has been updated by the branch. Here are the details:</p>

    <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
        <tr>
            <td style="padding: 8px 0; color: #8B7355; font-weight: bold;">Previous Schedule</td>
            <td style="padding: 8px 0; text-decoration: line-through; color: #999;">
                {{ $oldDateFormatted }} at {{ $oldTimeFormatted }}
            </td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #8B7355; font-weight: bold;">New Schedule</td>
            <td style="padding: 8px 0; font-weight: bold; color: #3C2F23;">
                {{ $newDateFormatted }} at {{ $newTimeFormatted }}
            </td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #8B7355; font-weight: bold;">Treatment</td>
            <td style="padding: 8px 0;">{{ $booking->treatment_label ?? $booking->treatment }}</td>
        </tr>
    </table>

    <p>If this new schedule doesn't work for you, please contact the branch directly to make arrangements.</p>

    <p style="margin-top: 24px; color: #999; font-size: 12px;">This is an automated notification from Levictas.</p>
</div>
