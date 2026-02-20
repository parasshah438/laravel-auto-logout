<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login OTP</title>
</head>
<body style="margin:0;padding:0;background:#f6f8fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 24px 12px;">
                            <h2 style="margin:0 0 8px;font-size:20px;line-height:1.3;">Verify Your Sign In</h2>
                            <p style="margin:0;color:#4b5563;font-size:14px;line-height:1.6;">
                                Use the one-time password below to continue signing in.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 24px 8px;">
                            <div style="display:inline-block;padding:12px 18px;border-radius:8px;background:#f3f4f6;border:1px solid #d1d5db;font-size:28px;letter-spacing:6px;font-weight:700;color:#111827;">
                                {{ $otp }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 24px 24px;">
                            <p style="margin:0 0 10px;color:#4b5563;font-size:14px;line-height:1.6;">
                                This OTP will expire in {{ $expiresInMinutes }} minutes.
                            </p>
                            <p style="margin:0;color:#6b7280;font-size:12px;line-height:1.6;">
                                If you did not request this, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
