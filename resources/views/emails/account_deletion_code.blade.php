<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f4f5;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);overflow:hidden;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#1e3a5f 0%,#2d5a87 100%);padding:28px 32px;text-align:center;">
                            <span style="font-size:22px;font-weight:700;color:#ffffff;letter-spacing:-0.5px;">Diplomasi</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 32px 24px;">
                            <h1 style="margin:0 0 8px;font-size:18px;font-weight:600;color:#1a1a2e;line-height:1.4;">
                                رمز حذف حسابك
                            </h1>
                            <p style="margin:0 0 24px;font-size:15px;color:#4a5568;line-height:1.6;">
                                مرحباً {{ $userName }}،
                            </p>
                            <p style="margin:0 0 20px;font-size:15px;color:#4a5568;line-height:1.6;">
                                طلبت حذف حسابك. استخدم الرمز أدناه لإتمام عملية الحذف.
                            </p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:24px 0;">
                                <tr>
                                    <td align="center" style="padding:20px;background:#f8fafc;border-radius:10px;border:2px dashed #cbd5e1;">
                                        <span style="font-size:28px;font-weight:700;color:#1e3a5f;letter-spacing:8px;font-variant-numeric:tabular-nums;">{{ $code }}</span>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0;font-size:14px;color:#64748b;line-height:1.6;">
                                صالح لمدة <strong>{{ $minutes }}</strong> دقائق. لا تشارك هذا الرمز مع أي شخص.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px 28px;border-top:1px solid #e2e8f0;">
                            <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.5;text-align:center;">
                                هذه رسالة تلقائية من دبلوماسي. في حال لم تطلب حذف الحساب، يرجى تجاهل الرسالة وتأمين حسابك.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
