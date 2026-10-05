<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Verify Your Email | Levictas Spa & Wellness</title>
</head>

<body style="margin:0; padding:0; background:#F8F5F1; font-family:Arial, Helvetica, sans-serif; color:#2D3748;">

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#F8F5F1; padding:32px 16px;">
    <tr>
        <td align="center">

            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
                style="max-width:600px; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,0.06);">

                <tr>
                    <td align="center" style="padding:32px 32px 20px; background:#ffffff;">

                        <img
                            src="{{ asset('images/1.png') }}"
                            alt="Levictas Spa & Wellness"
                            width="70"
                            style="display:block; width:70px; height:auto; border-radius:10px; margin-bottom:18px;">

                        <div style="font-family:Georgia, 'Times New Roman', serif; font-size:26px; color:#2D3748; font-weight:600;">
                            Levictas Spa & Wellness
                        </div>

                        <div style="margin-top:8px; font-size:13px; color:#8B7355; letter-spacing:1.5px; text-transform:uppercase;">
                            Email Verification
                        </div>

                    </td>
                </tr>

                <tr>
                    <td style="padding:16px 40px 40px;">

                        <h1 style="margin:0 0 12px; font-family:Georgia, 'Times New Roman', serif; font-size:24px; font-weight:600; color:#2D3748;">
                            Welcome to Levictas!
                        </h1>

                        <p style="margin:0 0 24px; font-size:14px; line-height:1.7; color:#6B7280;">
                            Please verify your email address before your Levictas account is created.
                            You can verify your registration using the button below or by entering the
                            6-digit verification code.
                        </p>

                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                            <tr>
                                <td align="center" style="padding:4px 0 28px;">

                                    <a
                                        href="{{ $verificationUrl }}"
                                        style="display:inline-block; padding:14px 28px; background:#6F5430; color:#ffffff; text-decoration:none; border-radius:12px; font-size:14px; font-weight:600;">
                                        Verify Email Address
                                    </a>

                                </td>
                            </tr>
                        </table>

                        <div style="text-align:center; font-size:12px; color:#9CA3AF; margin-bottom:14px;">
                            OR VERIFY USING YOUR CODE
                        </div>

                        <div style="background:#F8F5F1; border:1px solid #E7DED3; border-radius:14px; padding:22px; text-align:center;">

                            <div style="font-size:12px; color:#6B7280; margin-bottom:8px;">
                                Your verification code
                            </div>

                            <div style="font-size:32px; font-weight:700; letter-spacing:8px; color:#6F5430;">
                                {{ $otp }}
                            </div>

                            <div style="margin-top:10px; font-size:12px; color:#9CA3AF;">
                                This code expires in 10 minutes.
                            </div>

                        </div>

                        <div style="margin-top:26px; padding:16px; background:#FFFBEB; border:1px solid #FDE68A; border-radius:12px;">

                            <p style="margin:0; font-size:12px; line-height:1.6; color:#92400E;">
                                The verification link expires in 60 minutes.
                                Your registration will remain pending for up to 24 hours.
                            </p>

                        </div>

                        <p style="margin:26px 0 0; font-size:13px; line-height:1.7; color:#6B7280;">
                            If you did not request this registration, you can safely ignore this email.
                        </p>

                        <p style="margin:22px 0 0; font-size:13px; line-height:1.7; color:#6B7280;">
                            Regards,<br>
                            <strong style="color:#2D3748;">Levictas Spa & Wellness</strong>
                        </p>

                        <div style="height:1px; background:#E5E7EB; margin:30px 0 20px;"></div>

                        <p style="margin:0 0 8px; font-size:11px; line-height:1.6; color:#9CA3AF;">
                            If the button above does not work, copy and paste this link into your browser:
                        </p>

                        <p style="margin:0; font-size:11px; line-height:1.6; word-break:break-all;">
                            <a href="{{ $verificationUrl }}" style="color:#8B7355;">
                                {{ $verificationUrl }}
                            </a>
                        </p>

                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:20px 32px; background:#F8F5F1;">

                        <p style="margin:0; font-size:11px; color:#9C8B78;">
                            © {{ date('Y') }} Levictas Spa & Wellness
                        </p>

                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
