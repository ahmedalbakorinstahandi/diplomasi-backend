<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Completion</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: dejavusans, Helvetica, sans-serif;
            direction: ltr;
            width: 297mm;
            height: 210mm;
            margin: 0;
            background: #ffffff;
            color: #1a1a2e;
        }

        /* padding أوضح للورقة — الصفحة متناسقة وممتلئة */
        .certificate-page {
            position: relative;
            width: 297mm;
            height: 210mm;
            background-color: #ffffff;
            padding: 18mm 20mm 18mm 20mm;
        }

        .template-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 297mm;
            height: 210mm;
            z-index: 0;
        }

        .app-logo {
            position: absolute;
            top: 18mm;
            right: 20mm;
            width: 28mm;
            z-index: 3;
        }
        .app-logo img {
            width: 100%;
            height: auto;
            max-height: 14mm;
        }

        /* المحتوى المركزي: أكبر ومحاذي بالنص — تصميم ممتلئ */
        .main-content {
            position: relative;
            z-index: 2;
            text-align: center;
            padding-top: 4mm;
            padding-bottom: 8mm;
            max-width: 100%;
        }

        .intro-text {
            font-size: 14pt;
            color: #333333;
            margin-bottom: 5mm;
        }

        .recipient-name {
            font-size: 24pt;
            font-weight: bold;
            color: #1a1a2e;
            margin-bottom: 8mm;
            line-height: 1.25;
        }

        .badge-wrap {
            margin-bottom: 6mm;
        }

        .badge-line {
            font-size: 12pt;
            font-weight: bold;
            color: #ffffff;
            background-color: #1e3a5f;
            padding: 7px 24px;
            border-radius: 5px;
        }

        .completion-statement {
            font-size: 12pt;
            color: #2d2d2d;
            margin-bottom: 5mm;
        }

        .program-display {
            font-size: 17pt;
            color: #1a1a2e;
            line-height: 1.35;
        }

        .footer-left {
            position: absolute;
            bottom: 18mm;
            left: 20mm;
            font-size: 9.5pt;
            color: #333333;
            z-index: 2;
        }

        .footer-left .date-line { margin-bottom: 4px; }
        .footer-left .date-value { border-bottom: 1px solid #333; padding: 2px 5px; }
        .footer-left .no-line { margin-bottom: 4px; }
        .footer-left .no-value { border: 1px solid #333; padding: 2px 5px; font-family: monospace; font-size: 8.5pt; }
        .footer-left .provider { margin-top: 5px; }
        .footer-left .provider-label { font-weight: bold; }

        .footer-right {
            position: absolute;
            bottom: 18mm;
            right: 20mm;
            text-align: right;
            font-size: 9.5pt;
            color: #333333;
            z-index: 2;
        }

        .signatory-name { font-weight: bold; }
        .signatory-title { font-size: 8.5pt; margin-top: 2px; }

        .qr-code {
            margin-top: 8px;
            width: 22mm;
            height: 22mm;
        }

        .qr-code img {
            width: 22mm;
            height: 22mm;
        }
    </style>
</head>
<body>
    <div class="certificate-page">
        @if(!empty($template_image_data_uri))
        <img class="template-bg" src="{{ $template_image_data_uri }}" alt="">
        @endif
        @if(!empty($show_app_logo) && !empty($app_logo_data_uri))
        <div class="app-logo">
            <img src="{{ $app_logo_data_uri }}" alt="">
        </div>
        @endif

        <div class="main-content">
            <p class="intro-text">This document certifies that</p>
            <div class="recipient-name">{{ $recipient_name_en ?? '—' }}</div>
            <div class="badge-wrap">
                <span class="badge-line">HAS COMPLETED</span>
            </div>
            <p class="completion-statement">{{ $completion_statement ?? '' }}</p>
            <p class="program-display">{{ $program_display ?? '—' }}</p>
        </div>

        <div class="footer-left">
            <div class="date-line">DATE <span class="date-value">{{ $issued_date_en ?? '—' }}</span></div>
            <div class="no-line">NO. <span class="no-value">{{ $certificate_code ?? '—' }}</span></div>
            <div class="provider">
                <span class="provider-label">Training Provider:</span> {{ $training_provider ?? 'Diplomasi' }}
            </div>
            <div class="provider">
                <span class="provider-label">Exam Provider:</span> {{ $exam_provider ?? 'Diplomasi' }}
            </div>
        </div>

        <div class="footer-right">
            <div class="signatory-name">Stavros C. Fatta – MD OF CERTIFICATE</div>
            <div class="signatory-title">CORPORATE PROGRAMMES</div>
            @if(!empty($qr_code_data_uri))
            <div class="qr-code">
                <img src="{{ $qr_code_data_uri }}" alt="">
            </div>
            @endif
        </div>
    </div>
</body>
</html>
