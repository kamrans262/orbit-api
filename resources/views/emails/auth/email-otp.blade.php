<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orbit verification code</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#172033;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4f6f8;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#ffffff;border-radius:20px;box-shadow:0 12px 36px rgba(23,32,51,.08);overflow:hidden;">
                <tr>
                    <td style="padding:34px 36px 18px;text-align:center;">
                        <div style="font-size:30px;font-weight:800;letter-spacing:-1px;">Orbit</div>
                        <div style="margin-top:8px;font-size:15px;color:#6d7688;">Private connection. Simple verification.</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:14px 36px 8px;">
                        <div style="font-size:22px;font-weight:700;text-align:center;">Your verification code</div>
                        <div style="margin-top:12px;font-size:15px;line-height:1.6;color:#596273;text-align:center;">Enter this code in Orbit to continue signing in.</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:22px 36px;text-align:center;">
                        <div style="display:inline-block;padding:18px 28px;border-radius:16px;background:#f0f3f8;font-size:34px;font-weight:800;letter-spacing:10px;color:#172033;">{{ $otp }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 36px 34px;text-align:center;font-size:14px;line-height:1.6;color:#6d7688;">
                        This code expires at {{ $expiresAt->format('H:i') }} UTC. If you did not request this code, you can safely ignore this email.
                    </td>
                </tr>
            </table>
            <div style="padding-top:18px;font-size:12px;color:#8b93a3;">Orbit authentication</div>
        </td>
    </tr>
</table>
</body>
</html>
