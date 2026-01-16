<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الشهادة</title>
    <style>
        /* الخط العربي يتم تسجيله في mPDF مباشرة - لا حاجة لـ @font-face */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'dejavusans', 'Cairo', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #FFFFFF;
            width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
            direction: rtl;
            text-align: right;
        }

        .certificate-container {
            width: 100%;
            height: 100%;
            position: relative;
            padding: 60px 80px;
            box-sizing: border-box;
        }

        .certificate-title {
            text-align: center;
            font-size: 32px;
            color: #1a1a5e;
            margin-bottom: 40px;
            font-weight: normal;
        }

        .certificate-text {
            text-align: center;
            font-size: 28px;
            color: #1a1a5e;
            margin-bottom: 30px;
            line-height: 1.8;
        }

        .user-name {
            text-align: center;
            font-size: 64px;
            color: #1a1a5e;
            margin: 40px 0;
            font-weight: normal;
            line-height: 1.5;
        }

        .course-title {
            text-align: center;
            font-size: 52px;
            color: #D4A017;
            margin: 30px 0;
            font-weight: normal;
            line-height: 1.6;
        }

        .company-text {
            text-align: center;
            font-size: 24px;
            color: #1a1a5e;
            margin: 30px 0;
            line-height: 1.8;
        }

        .training-hours {
            text-align: center;
            font-size: 28px;
            color: #1a1a5e;
            margin: 30px 0;
            line-height: 1.8;
        }

        .date {
            position: absolute;
            bottom: 50px;
            left: 80px;
            font-size: 20px;
            color: #1a1a5e;
            direction: rtl;
        }

        .qr-code {
            position: absolute;
            bottom: 40px;
            right: 60px;
            width: 150px;
            height: 150px;
        }

        .qr-code img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <div class="certificate-title">
            تمنح هذه الشهادة الى:
        </div>

        <div class="user-name">
            {{ $user_name }}
        </div>

        <div class="certificate-text">
            وذلك لحضوره / ها الدورة التدريبية بعنوان:
        </div>

        <div class="course-title">
            {{ $course_title }}
        </div>

        <div class="company-text">
            التي اقامتها شركة دبلوماسي - diplomasi وذلك ضمن برامجها وفعالياتها الريادية
        </div>

        <div class="training-hours">
            بمدة تدريبية قدرها {{ $hours_text }} ({{ $hours }}) ساعة تدريبية
        </div>

        @if($qr_code_path && file_exists($qr_code_path))
        <div class="qr-code">
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents($qr_code_path)) }}" alt="QR Code">
        </div>
        @endif

        <div class="date">
            التاريخ: {{ $date }}
        </div>
    </div>
</body>
</html>
