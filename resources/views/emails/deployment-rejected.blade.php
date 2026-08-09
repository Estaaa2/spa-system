{{-- resources/views/emails/deployment-rejected.blade.php --}}
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.5;">
    <h2 style="color: #b91c1c;">Branch Deployment Request — Not Approved</h2>

    <p>Hi {{ $deployment->staff->user->first_name ?? 'there' }},</p>

    <p>Your branch deployment request has <strong>not been approved</strong>. Details below:</p>

    <table cellpadding="8" style="border-collapse: collapse; width: 100%; max-width: 500px;">
        <tr style="background: #f9f5f0;">
            <td><strong>From Branch:</strong></td>
            <td>{{ $deployment->fromBranch->name }} — {{ $deployment->fromBranch->location }}</td>
        </tr>
        <tr>
            <td><strong>Requested Branch:</strong></td>
            <td>{{ $deployment->toBranch->name }} — {{ $deployment->toBranch->location }}</td>
        </tr>
        <tr style="background: #f9f5f0;">
            <td><strong>Requested Start Date:</strong></td>
            <td>{{ \Carbon\Carbon::parse($deployment->start_date)->format('F d, Y') }}</td>
        </tr>
    </table>

    <p style="margin-top: 16px;"><strong>Reason:</strong><br>{{ $deployment->rejection_reason }}</p>

    <p style="margin-top: 20px;">Please reach out to HR if you have questions or would like to submit a new request.</p>

    <p style="color: #999; font-size: 12px; margin-top: 30px;">This is an automated notification. Please do not reply directly to this email.</p>
</body>
</html>
