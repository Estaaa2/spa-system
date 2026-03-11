
<table width="100%" cellpadding="0" cellspacing="0" style="padding: 20px 0;">
    <tr>
        <td align="center">

            <!-- Table Main Container -->
            <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow: hidden;">

                <!-- Header -->
                <tr>
                    <td style="background-color: #8B7355; color: white; text-align: center; padding: 25px;">
                        <h1 style="margin:0; font-size: 24px;">Welcome to {{ $user->spa->name }}!</h1>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding: 25px; color: #333333; font-size: 16px; line-height: 1.5;">
                        <p>Hello <strong>{{ $user->name }}</strong>,</p>

                        <p>Your staff account has been created. Here are your login credentials:</p>


                        <table cellpadding="0" cellspacing="0" style="width:100%; margin: 15px 0; border: 1px solid #e2e2e2; border-radius: 6px; background-color: #fafafa;">
                            <tr>
                                <td style="padding: 15px;">
                                    <p style="margin: 5px 0;"><strong>Email:</strong> {{ $user->email }}</p>
                                    <p style="margin: 5px 0;"><strong>Temporary Password:</strong> {{ $tempPassword }}</p>
                                </td>
                            </tr>
                        </table>

                        <p>Please <a href="{{ route('login') }}" style="color: #8B7355; font-weight: bold;">log in</a> and change your password immediately.</p>

                        <p style="margin-top: 25px; font-size: 14px; color: #555555;">
                            Thank you,<br>
                            <strong>{{ $user->spa->name }} Team</strong>
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="background-color: #f0f0f0; color: #888888; text-align: center; padding: 15px; font-size: 12px;">
                        © {{ date('Y') }} Levictas. All rights reserved.
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>