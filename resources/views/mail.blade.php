<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Template</title>
</head>
<body style="margin:0; padding:0; background:#f4f4f4; font-family:Arial, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4; padding:20px 0;">
        <tr>
            <td align="center">

                <!-- Main Container -->
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; overflow:hidden;">

                    <!-- Header -->
                    <tr>
                        <td align="center" style="background:#2563eb; color:#ffffff; padding:25px;">
                            <h1 style="margin:0;">My Company</h1>
                            <p style="margin:5px 0 0;">Professional Email Notification</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding:30px;">
                            <h2 style="color:#333;">Hello,</h2>

                            <p style="font-size:16px; color:#555; line-height:1.6;">
                                {{ $msg }}
                            </p>

                            <p style="font-size:14px; color:#888;">
                                Sent on: {{ now()->format('d M Y h:i A') }}
                            </p>

                            <div style="margin-top:30px;">
                                <a href="#" style="background:#2563eb; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:5px; display:inline-block;">
                                    View Details
                                </a>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background:#f8f9fa; padding:20px; color:#777; font-size:13px;">
                            <p style="margin:0;">
                                © {{ date('Y') }} My Company. All rights reserved.
                            </p>
                            <p style="margin:5px 0 0;">
                                123 Business Street, City, Country
                            </p>
                            <p style="margin:5px 0 0;">
                                support@mycompany.com | +91 9876543210
                            </p>
                        </td>
                    </tr>

                </table>
                <!-- End Container -->

            </td>
        </tr>
    </table>

</body>
</html>