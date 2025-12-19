<?php

namespace Database\Seeders;

use App\Models\Progress\UserCourse;
use App\Models\System\Certificate;
use Illuminate\Database\Seeder;

class CertificateSeeder extends Seeder
{
    public function run(): void
    {
        $completed = UserCourse::query()
            ->where('status', 'completed')
            ->with(['course.levels'])
            ->limit(10)
            ->get();

        foreach ($completed as $uc) {
            $level = $uc->course?->levels?->sortByDesc('level_number')->first();
            $levelId = $level?->id;

            $code = 'CERT-' . $uc->user_id . '-' . $uc->course_id . '-' . ($levelId ?? 0);

            Certificate::withTrashed()->updateOrCreate(
                [
                    'user_id' => $uc->user_id,
                    'course_id' => $uc->course_id,
                    'level_id' => $levelId,
                ],
                [
                    'certificate_code' => $code,
                    'issued_at' => now()->subDays(1),
                    'qr_code' => 'qr/' . $code . '.png',
                    'pdf_url' => 'certificates/' . $code . '.pdf',
                    'deleted_at' => null,
                ]
            );
        }
    }
}

