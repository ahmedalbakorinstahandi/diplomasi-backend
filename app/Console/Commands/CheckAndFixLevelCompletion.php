<?php

namespace App\Console\Commands;

use App\Models\Learning\Level;
use App\Models\Learning\LevelTrack;
use App\Models\Progress\UserLevelProgress;
use App\Services\TrackProgressService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckAndFixLevelCompletion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:check-level {level_id} {user_id?} {--fix}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'فحص حالة إكمال المستوى وإصلاحها إذا لزم الأمر';

    protected TrackProgressService $trackProgressService;

    public function __construct(TrackProgressService $trackProgressService)
    {
        parent::__construct();
        $this->trackProgressService = $trackProgressService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $levelId = $this->argument('level_id');
        $userId = $this->argument('user_id');
        $fix = $this->option('fix');

        $level = Level::find($levelId);
        if (!$level) {
            $this->error("المستوى {$levelId} غير موجود");
            return 1;
        }

        $this->info("فحص المستوى: {$level->title} (ID: {$levelId})");
        $this->info("has_certificate: " . ($level->has_certificate ? 'نعم' : 'لا'));
        $this->line('');

        if ($userId) {
            // فحص مستخدم محدد
            $this->checkUserLevel($levelId, $userId, $fix);
        } else {
            // فحص جميع المستخدمين
            $userLevelProgresses = UserLevelProgress::where('level_id', $levelId)->get();
            
            if ($userLevelProgresses->isEmpty()) {
                $this->warn("لا يوجد مستخدمين مسجلين في هذا المستوى");
                return 0;
            }

            $this->info("عدد المستخدمين: " . $userLevelProgresses->count());
            $this->line('');

            foreach ($userLevelProgresses as $userLevelProgress) {
                $this->checkUserLevel($levelId, $userLevelProgress->user_id, $fix);
                $this->line('');
            }
        }

        return 0;
    }

    private function checkUserLevel(int $levelId, int $userId, bool $fix = false): void
    {
        $level = Level::find($levelId);
        $userLevelProgress = UserLevelProgress::where('user_id', $userId)
            ->where('level_id', $levelId)
            ->first();

        $this->info("المستخدم ID: {$userId}");

        if (!$userLevelProgress) {
            $this->warn("  ❌ UserLevelProgress غير موجود");
            if ($fix) {
                $this->info("  🔧 محاولة إنشاء UserLevelProgress...");
                $this->trackProgressService->checkAndUpdateLevelCompletion($levelId, $userId);
                $this->info("  ✅ تم استدعاء checkAndUpdateLevelCompletion");
            }
            return;
        }

        $this->info("  status: {$userLevelProgress->status}");
        $this->info("  completed_at: " . ($userLevelProgress->completed_at ? $userLevelProgress->completed_at->format('Y-m-d H:i:s') : 'null'));
        $this->info("  score: {$userLevelProgress->score}");

        // فحص إكمال العناصر
        $levelTracks = LevelTrack::where('level_id', $levelId)
            ->with('trackable')
            ->orderBy('order_index')
            ->get();

        $this->line('');
        $this->info("  العناصر في المستوى:");
        
        $allCompleted = true;
        $completedLessons = 0;
        $completedScenarios = 0;
        $totalLessons = 0;
        $totalScenarios = 0;
        
        foreach ($levelTracks as $track) {
            $trackable = $track->trackable;
            if (!$trackable) {
                continue;
            }
            
            if (!$this->trackProgressService->isTrackablePublished($trackable)) {
                continue;
            }

            $isCompleted = $this->trackProgressService->isTrackCompleted($trackable, $userId);
            $type = $trackable instanceof \App\Models\Learning\Lesson ? 'درس' : 'سيناريو';
            $title = $trackable->title ?? 'غير محدد';
            
            if ($trackable instanceof \App\Models\Learning\Lesson) {
                $totalLessons++;
                if ($isCompleted) {
                    $completedLessons++;
                }
            } else {
                $totalScenarios++;
                if ($isCompleted) {
                    $completedScenarios++;
                }
            }
            
            $status = $isCompleted ? '✅' : '❌';
            $this->line("    {$status} [{$type}] {$title}");
            
            if (!$isCompleted) {
                $allCompleted = false;
            }
        }
        
        $this->line('');
        $this->info("  ملخص: {$completedLessons}/{$totalLessons} دروس، {$completedScenarios}/{$totalScenarios} سيناريوهات");

        $this->line('');
        
        if ($allCompleted && $userLevelProgress->status !== 'completed') {
            $this->warn("  ⚠️  جميع العناصر مكتملة ولكن UserLevelProgress.status !== 'completed'");
            if ($fix) {
                $this->info("  🔧 إصلاح UserLevelProgress...");
                $this->trackProgressService->checkAndUpdateLevelCompletion($levelId, $userId);
                $userLevelProgress->refresh();
                $this->info("  ✅ status الآن: {$userLevelProgress->status}");
            }
        } elseif (!$allCompleted && $userLevelProgress->status === 'completed') {
            $this->warn("  ⚠️  UserLevelProgress.status = 'completed' ولكن بعض العناصر غير مكتملة");
        } elseif ($allCompleted && $userLevelProgress->status === 'completed') {
            $this->info("  ✅ المستوى مكتمل بشكل صحيح");
            
            // فحص الشهادة
            $certificate = \App\Models\System\Certificate::where('user_id', $userId)
                ->where('level_id', $levelId)
                ->first();
            
            if ($level->has_certificate) {
                if (!$certificate) {
                    $this->warn("  ⚠️  المستوى يحتوي على شهادة ولكن لم يتم إصدارها");
                    if ($fix) {
                        $this->info("  🔧 محاولة إصدار الشهادة...");
                        try {
                            $certificateService = app(\App\Http\Services\System\CertificateService::class);
                            $certificateService->issueCertificate($userId, $level->course_id, $levelId);
                            $this->info("  ✅ تم إصدار الشهادة بنجاح");
                        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
                            $this->error("  ❌ المستخدم غير مؤهل للحصول على الشهادة");
                            Log::error("Certificate issuance error in command", [
                                'user_id' => $userId,
                                'level_id' => $levelId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                } else {
                    $this->info("  ✅ الشهادة موجودة: {$certificate->certificate_code}");
                }
            }
        }
    }
}
