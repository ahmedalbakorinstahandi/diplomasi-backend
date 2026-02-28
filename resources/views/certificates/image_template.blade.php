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
            width: 100%;
            min-height: 100%;
            position: relative;
            overflow: hidden;
        }

        .certificate-page {
            position: relative;
            width: 100%;
            min-height: 297mm;
            background-color: #fafafa;
        }

        .template-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
        }

        .certificate-content {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            padding: 8% 10%;
            z-index: 2;
        }

        .intro-text {
            text-align: center;
            font-size: 14pt;
            color: #333;
            margin-top: 18%;
            margin-bottom: 4px;
        }

        .recipient-name {
            text-align: center;
            font-size: 22pt;
            font-weight: bold;
            color: #1a1a2e;
            margin-bottom: 20px;
            line-height: 1.3;
        }

        .badge-line {
            text-align: center;
            font-size: 11pt;
            font-weight: bold;
            color: #fff;
            background-color: #1e3a5f;
            display: inline-block;
            padding: 8px 24px;
            border-radius: 6px;
            margin: 0 auto 12px;
            width: auto;
        }

        .badge-wrap {
            text-align: center;
            margin-bottom: 8px;
        }

        .program-display {
            text-align: center;
            font-size: 16pt;
            color: #1a1a2e;
            margin-bottom: 24px;
            line-height: 1.4;
        }

        .completion-statement {
            text-align: center;
            font-size: 11pt;
            color: #2d2d2d;
            margin-bottom: 8px;
        }

        .footer-left {
            position: absolute;
            bottom: 12%;
            left: 10%;
            font-size: 9pt;
            color: #333;
        }

        .footer-left .date-line { margin-bottom: 8px; }
        .footer-left .date-value { border-bottom: 1px solid #333; padding: 2px 8px; min-width: 140px; display: inline-block; }
        .footer-left .no-line { margin-bottom: 6px; }
        .footer-left .no-value { border: 1px solid #333; padding: 2px 8px; min-width: 120px; display: inline-block; font-family: monospace; }
        .footer-left .provider { margin-top: 10px; }
        .footer-left .provider-label { font-weight: bold; }
        .footer-left .provider-value { margin-left: 4px; }

        .footer-right {
            position: absolute;
            bottom: 12%;
            right: 10%;
            text-align: right;
            font-size: 9pt;
            color: #333;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .signatory-name { font-weight: bold; }
        .signatory-title { font-size: 8pt; margin-top: 2px; }

        .qr-code {
            margin-top: 10px;
            width: 115px;
            height: 115px;
        }

        .qr-code img { width: 100%; height: 100%; object-fit: contain; }
    </style>
</head>
<body>
    <div class="certificate-page">
        @if(!empty($template_image_data_uri))
        <img class="template-bg" src="{{ $template_image_data_uri }}" alt="Certificate Template">
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
