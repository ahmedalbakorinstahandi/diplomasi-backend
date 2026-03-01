<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'dejavusans', 'Helvetica', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            direction: ltr;
            text-align: left;
            overflow: hidden;
        }

        /* A4 بالعرض: 297mm × 210mm — مقاس الصورة على قد المحتوى */
        .certificate-page {
            position: relative;
            width: 297mm;
            height: 210mm;
            background-color: #ffffff;
        }

        .template-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 297mm;
            height: 210mm;
            object-fit: cover;
            z-index: 0;
        }

        /* شعار التطبيق بالزاوية اليمنى دون التأثير على الشكل */
        .app-logo {
            position: absolute;
            top: 12mm;
            right: 14mm;
            width: 28mm;
            height: auto;
            max-height: 14mm;
            z-index: 3;
        }
        .app-logo img {
            width: 100%;
            height: auto;
            object-fit: contain;
        }

        .certificate-content {
            position: absolute;
            top: 0;
            left: 0;
            width: 297mm;
            height: 210mm;
            padding: 10mm 14mm 12mm 14mm;
            z-index: 2;
        }

        .intro-text {
            text-align: center;
            font-size: 12pt;
            color: #333;
            margin-top: 8mm;
            margin-bottom: 2mm;
        }

        .recipient-name {
            text-align: center;
            font-size: 20pt;
            font-weight: bold;
            color: #1a1a2e;
            margin-bottom: 10mm;
            line-height: 1.25;
        }

        .badge-line {
            text-align: center;
            font-size: 10pt;
            font-weight: bold;
            color: #fff;
            background-color: #1e3a5f;
            display: inline-block;
            padding: 6px 20px;
            border-radius: 6px;
            margin: 0 auto 6px;
        }

        .badge-wrap {
            text-align: center;
            margin-bottom: 4mm;
        }

        .completion-statement {
            text-align: center;
            font-size: 10pt;
            color: #2d2d2d;
            margin-bottom: 4mm;
        }

        .program-display {
            text-align: center;
            font-size: 14pt;
            color: #1a1a2e;
            margin-bottom: 14mm;
            line-height: 1.3;
        }

        .footer-left {
            position: absolute;
            bottom: 14mm;
            left: 14mm;
            font-size: 8.5pt;
            color: #333;
        }

        .footer-left .date-line { margin-bottom: 4px; }
        .footer-left .date-value { border-bottom: 1px solid #333; padding: 2px 6px; min-width: 100px; display: inline-block; }
        .footer-left .no-line { margin-bottom: 4px; }
        .footer-left .no-value { border: 1px solid #333; padding: 2px 6px; min-width: 90px; display: inline-block; font-family: monospace; font-size: 7.5pt; }
        .footer-left .provider { margin-top: 6px; }
        .footer-left .provider-label { font-weight: bold; }
        .footer-left .provider-value { margin-left: 4px; }

        .footer-right {
            position: absolute;
            bottom: 14mm;
            right: 14mm;
            text-align: right;
            font-size: 8.5pt;
            color: #333;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .signatory-name { font-weight: bold; }
        .signatory-title { font-size: 7.5pt; margin-top: 2px; }

        .qr-code {
            margin-top: 8px;
            width: 22mm;
            height: 22mm;
        }

        .qr-code img { width: 100%; height: 100%; object-fit: contain; }
    </style>
</head>
<body>
    <div class="certificate-page">
        @if(!empty($template_image_data_uri))
        <img class="template-bg" src="{{ $template_image_data_uri }}" alt="Certificate Template">
        @endif
        @if(!empty($show_app_logo) && !empty($app_logo_data_uri))
        <div class="app-logo">
            <img src="{{ $app_logo_data_uri }}" alt="App Logo">
        </div>
        @endif
        <div class="certificate-content">
            <p class="intro-text">This document certifies that</p>
            <div class="recipient-name">{{ $recipient_name_en ?? '—' }}</div>
            <div class="badge-wrap">
                <span class="badge-line">HAS COMPLETED</span>
            </div>
            <p class="completion-statement">{{ $completion_statement ?? '' }}</p>
            <p class="program-display">{{ $program_display ?? '—' }}</p>

            <div class="footer-left">
                <div class="date-line">DATE <span class="date-value">{{ $issued_date_en ?? '—' }}</span></div>
                <div class="no-line">NO. <span class="no-value">{{ $certificate_code ?? '—' }}</span></div>
                <div class="provider">
                    <span class="provider-label">Training Provider:</span><span class="provider-value">{{ $training_provider ?? 'Diplomasi' }}</span>
                </div>
                <div class="provider">
                    <span class="provider-label">Exam Provider:</span><span class="provider-value">{{ $exam_provider ?? 'Diplomasi' }}</span>
                </div>
            </div>

            <div class="footer-right">
                <div class="signatory-name">Stavros C. Fatta – MD OF CERTIFICATE</div>
                <div class="signatory-title">CORPORATE PROGRAMMES</div>
                @if(!empty($qr_code_data_uri))
                <div class="qr-code">
                    <img src="{{ $qr_code_data_uri }}" alt="QR Code">
                </div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
