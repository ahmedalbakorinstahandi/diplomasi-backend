<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التحقق من الشهادة - {{ $certificate_code }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 900px;
            width: 100%;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header .badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            margin-top: 10px;
        }

        .content {
            padding: 40px;
        }

        .certificate-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: bold;
            color: #555;
            font-size: 16px;
        }

        .info-value {
            color: #333;
            font-size: 16px;
            text-align: right;
            flex: 1;
            margin-right: 20px;
        }

        .certificate-image {
            text-align: center;
            margin: 30px 0;
        }

        .certificate-image img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .no-image {
            background: #f0f0f0;
            padding: 40px;
            border-radius: 10px;
            color: #999;
            font-size: 18px;
        }

        .verify-badge {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            display: inline-block;
            font-weight: bold;
            margin: 20px 0;
        }

        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }

        .qr-code {
            text-align: center;
            margin: 30px 0;
        }

        .qr-code img {
            max-width: 200px;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .info-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .info-value {
                margin-right: 0;
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✓ شهادة صحيحة</h1>
            <div class="badge">تم التحقق من الشهادة بنجاح</div>
        </div>

        <div class="content">
            <div class="certificate-info">
                <div class="info-row">
                    <span class="info-label">👤 اسم المستخدم:</span>
                    <span class="info-value">{{ $user_name }}</span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">📚 اسم الكورس:</span>
                    <span class="info-value">{{ $course_title }}</span>
                </div>

                @if($level_title)
                <div class="info-row">
                    <span class="info-label">📖 اسم المستوى:</span>
                    <span class="info-value">{{ $level_title }}</span>
                </div>
                @endif

                <div class="info-row">
                    <span class="info-label">📅 تاريخ الإصدار:</span>
                    <span class="info-value">{{ $issued_at }}</span>
                </div>

                <div class="info-row">
                    <span class="info-label">🏷️ كود الشهادة:</span>
                    <span class="info-value" style="font-family: monospace; direction: ltr; text-align: left;">{{ $certificate_code }}</span>
                </div>
            </div>

            @php
                use App\Services\MediaUrlService;
                $imageUrl = $certificate->image_url ? MediaUrlService::toUrl($certificate->image_url) : null;
                $qrCodeUrl = $certificate->qr_code ? MediaUrlService::toUrl($certificate->qr_code) : null;
            @endphp

            @if($imageUrl)
            <div class="certificate-image">
                <img src="{{ $imageUrl }}" alt="صورة الشهادة" onerror="this.parentElement.innerHTML='<div class=\'no-image\'>📄 صورة الشهادة غير متاحة حالياً</div>'">
            </div>
            @else
            <div class="certificate-image">
                <div class="no-image">
                    📄 صورة الشهادة غير متاحة حالياً
                </div>
            </div>
            @endif

            @if($qrCodeUrl)
            <div class="qr-code">
                <h3 style="margin-bottom: 15px; color: #555;">QR Code للتحقق:</h3>
                <img src="{{ $qrCodeUrl }}" alt="QR Code" onerror="this.style.display='none'">
            </div>
            @endif
        </div>

        <div class="footer">
            <p>تم التحقق من هذه الشهادة في {{ now()->format('Y-m-d H:i:s') }}</p>
            <p style="margin-top: 10px;">© {{ date('Y') }} Diplomasi - جميع الحقوق محفوظة</p>
        </div>
    </div>
</body>
</html>
