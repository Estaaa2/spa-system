{{-- resources/views/emails/deployment-approved.blade.php --}}
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.5;">
    <h2 style="color: #8B7355;">Branch Deployment Notice</h2>

    <p>Hi {{ $deployment->staff->user->first_name ?? 'there' }},</p>

    <p>Your branch deployment request has been <strong>approved</strong>. Here are the details:</p>

    <table cellpadding="8" style="border-collapse: collapse; width: 100%; max-width: 500px;">
        <tr style="background: #f9f5f0;">
            <td><strong>From Branch:</strong></td>
            <td>{{ $deployment->fromBranch->name }} — {{ $deployment->fromBranch->location }}</td>
        </tr>
        <tr>
            <td><strong>To Branch:</strong></td>
            <td>{{ $deployment->toBranch->name }} — {{ $deployment->toBranch->location }}</td>
        </tr>
        <tr style="background: #f9f5f0;">
            <td><strong>Start Date:</strong></td>
            <td>{{ \Carbon\Carbon::parse($deployment->start_date)->format('F d, Y') }}</td>
        </tr>
        <tr>
            <td><strong>Duration:</strong></td>
            <td>
                {{ $deployment->is_permanent
                    ? 'Permanent transfer'
                    : ($deployment->end_date
                        ? 'Until ' . \Carbon\Carbon::parse($deployment->end_date)->format('F d, Y')
                        : 'Open-ended') }}
            </td>
        </tr>
    </table>

    @if($deployment->notes)
        <p style="margin-top: 16px;"><strong>Notes from HR:</strong><br>{{ $deployment->notes }}</p>
    @endif

    <p style="margin-top: 20px;">Please coordinate with your manager regarding your transition.</p>

    <p style="color: #999; font-size: 12px; margin-top: 30px;">This is an automated notification. Please do not reply directly to this email.</p>
</body>
</html>
