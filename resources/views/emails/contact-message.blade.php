<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رسالة تواصل جديدة</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f5;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);overflow:hidden;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#1e3a5f 0%,#2d5a87 100%);padding:28px 32px;text-align:center;">
                            <span style="font-size:22px;font-weight:700;color:#ffffff;">Diplomasi — رسالة تواصل</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 12px;font-size:15px;color:#4a5568;"><strong>الاسم:</strong> {{ $contactMessage->name }}</p>
                            <p style="margin:0 0 12px;font-size:15px;color:#4a5568;"><strong>البريد:</strong> {{ $contactMessage->email }}</p>
                            <p style="margin:0 0 12px;font-size:15px;color:#4a5568;"><strong>الموضوع:</strong> {{ $contactMessage->subject }}</p>
                            <p style="margin:0 0 8px;font-size:15px;color:#4a5568;"><strong>الرسالة:</strong></p>
                            <p style="margin:0;font-size:15px;color:#1a1a2e;line-height:1.6;white-space:pre-wrap;">{{ $contactMessage->message }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
