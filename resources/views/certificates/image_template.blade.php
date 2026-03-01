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

        .certificate-page {
            position: relative;
            width: 297mm;
            height: 210mm;
            background-color: #ffffff;
            padding: 10mm 14mm 14mm 14mm;
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
            top: 10mm;
            right: 14mm;
            width: 26mm;
            z-index: 3;
        }
        .app-logo img {
            width: 100%;
            height: auto;
            max-height: 12mm;
        }

        /* المحتوى الرئيسي: تدفق عادي حتى يظهر النص في mPDF */
        .main-content {
            position: relative;
            z-index: 2;
            text-align: center;
            padding-bottom: 2mm;
        }

        .intro-text {
            font-size: 12pt;
            color: #333333;
            margin-bottom: 3mm;
        }

        .recipient-name {
            font-size: 20pt;
            font-weight: bold;
            color: #1a1a2e;
            margin-bottom: 6mm;
            line-height: 1.2;
        }

        .badge-wrap {
            margin-bottom: 4mm;
        }

        .badge-line {
            font-size: 10pt;
            font-weight: bold;
            color: #ffffff;
            background-color: #1e3a5f;
            padding: 5px 18px;
            border-radius: 4px;
        }

        .completion-statement {
            font-size: 10pt;
            color: #2d2d2d;
            margin-bottom: 3mm;
        }

        .program-display {
            font-size: 14pt;
            color: #1a1a2e;
            line-height: 1.3;
        }

        .footer-left {
            position: absolute;
            bottom: 12mm;
            left: 14mm;
            font-size: 8.5pt;
            color: #333333;
            z-index: 2;
        }

        .footer-left .date-line { margin-bottom: 3px; }
        .footer-left .date-value { border-bottom: 1px solid #333; padding: 1px 4px; }
        .footer-left .no-line { margin-bottom: 3px; }
        .footer-left .no-value { border: 1px solid #333; padding: 1px 4px; font-family: monospace; font-size: 7.5pt; }
        .footer-left .provider { margin-top: 4px; }
        .footer-left .provider-label { font-weight: bold; }

        .footer-right {
            position: absolute;
            bottom: 12mm;
            right: 14mm;
            text-align: right;
            font-size: 8.5pt;
            color: #333333;
            z-index: 2;
        }

        .signatory-name { font-weight: bold; }
        .signatory-title { font-size: 7.5pt; margin-top: 1px; }

        .qr-code {
            margin-top: 6px;
            width: 20mm;
            height: 20mm;
        }

        .qr-code img {
            width: 20mm;
            height: 20mm;
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
