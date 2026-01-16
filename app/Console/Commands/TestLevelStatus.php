<?php

namespace App\Console\Commands;

use App\Models\Learning\Level;
use App\Models\Learning\LevelTrack;
use App\Models\Progress\UserLevelProgress;
use App\Models\Progress\UserLessonProgress;
use App\Models\Scenarios\UserScenarioAttempt;
use App\Services\TrackProgressService;
use Illuminate\Console\Command;

class TestLevelStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:level-status {user_id} {course_id?} {--level_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'اختبار حالة المستويات للمستخدم وعرض التفاصيل الكاملة';

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
        $userId = $this->argument('user_id');
        $courseId = $this->argument('course_id');
        $levelId = $this->option('level_id');

        $this->info("=== اختبار حالة المستويات ===");
        $this->info("المستخدم ID: {$userId}");
        $this->line('');

        // جلب المستويات
        $query = Level::query()->where('is_published', true);
        
        if ($courseId) {
            $query->where('course_id', $courseId);
        }
        
        if ($levelId) {
            $query->where('id', $levelId);
        }
        
        $levels = $query->orderBy('course_id')->orderBy('order_index', 'asc')->get();

        if ($levels->isEmpty()) {
            $this->warn("لا توجد مستويات للعرض");
            return 1;
        }

        $this->info("عدد المستويات: " . $levels->count());
        $this->line('');

        // استخدام loadProgressDataForLevels للحصول على البيانات
        $progressData = $this->trackProgressService->loadProgressDataForLevels($levels, $userId);

        // عرض النتائج
        foreach ($levels as $level) {
            $levelData = $progressData[$level->id] ?? null;
            
            if (!$levelData) {
                $this->error("⚠️  المستوى {$level->id} ({$level->title}) - لا توجد بيانات!");
                continue;
            }

            $this->displayLevelDetails($level, $userId, $levelData);
            $this->line('');
        }

        return 0;
    }

    private function displayLevelDetails(Level $level, int $userId, array $levelData): void
    {
        $isCompleted = $levelData['is_completed'] ?? false;
        $accessStatus = $levelData['access_status'] ?? 'unknown';
        $certificate = $levelData['certificate'] ?? null;

        // الألوان
        $statusColor = match($accessStatus) {
            'completed' => 'green',
            'open' => 'yellow',
            'locked' => 'red',
            default => 'white'
        };

        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("<fg=cyan>المستوى ID: {$level->id}</> | <fg=cyan>الكورس ID: {$level->course_id}</> | <fg=cyan>order_index: {$level->order_index}</>");
        $this->info("العنوان: {$level->title}");
        $this->line('');
        
        // حالة المستوى
        $this->line("<fg={$statusColor}>access_status: {$accessStatus}</>");
        $this->line("is_completed: " . ($isCompleted ? '<fg=green>true</>' : '<fg=red>false</>'));
        $this->line('');

        // UserLevelProgress
        $userLevelProgress = UserLevelProgress::where('user_id', $userId)
            ->where('level_id', $level->id)
            ->first();

        if ($userLevelProgress) {
            $this->line("<fg=cyan>UserLevelProgress:</>");
            $this->line("  status: {$userLevelProgress->status}");
            $this->line("  completed_at: " . ($userLevelProgress->completed_at ? $userLevelProgress->completed_at->format('Y-m-d H:i:s') : 'null'));
            $this->line("  score: {$userLevelProgress->score}");
        } else {
            $this->line("<fg=yellow>UserLevelProgress: غير موجود</>");
        }
        $this->line('');

        // المستوى السابق
        if ($level->course_id) {
            $courseLevels = Level::where('course_id', $level->course_id)
                ->where('is_published', true)
                ->orderBy('order_index', 'asc')
                ->get();

            $previousLevel = null;
            foreach ($courseLevels as $courseLevel) {
                if ($courseLevel->order_index < $level->order_index) {
                    if (!$previousLevel || $courseLevel->order_index > $previousLevel->order_index) {
                        $previousLevel = $courseLevel;
                    }
                }
            }

            if ($previousLevel) {
                $previousData = $progressData[$previousLevel->id] ?? null;
                $previousCompleted = $previousData['is_completed'] ?? false;
                
                $this->line("<fg=cyan>المستوى السابق:</>");
                $this->line("  ID: {$previousLevel->id} | order_index: {$previousLevel->order_index}");
                $this->line("  العنوان: {$previousLevel->title}");
                $this->line("  is_completed: " . ($previousCompleted ? '<fg=green>true</>' : '<fg=red>false</>'));
                
                $previousUserProgress = UserLevelProgress::where('user_id', $userId)
                    ->where('level_id', $previousLevel->id)
                    ->first();
                
                if ($previousUserProgress) {
                    $this->line("  UserLevelProgress.status: {$previousUserProgress->status}");
                } else {
                    $this->line("  UserLevelProgress: <fg=yellow>غير موجود</>");
                }
            } else {
                $this->line("<fg=cyan>المستوى السابق:</> <fg=green>لا يوجد (المستوى الأول)</>");
            }
        }
        $this->line('');

        // Tracks في المستوى
        $levelTracks = LevelTrack::where('level_id', $level->id)
            ->with('trackable')
            ->orderBy('order_index')
            ->get();

        if ($levelTracks->isNotEmpty()) {
            $this->line("<fg=cyan>العناصر في المستوى ({$levelTracks->count()}):</>");
            
            $publishedCount = 0;
            $completedCount = 0;
            
            foreach ($levelTracks as $track) {
                $trackable = $track->trackable;
                if (!$trackable) {
                    continue;
                }

                $isPublished = false;
                if ($trackable instanceof \App\Models\Learning\Lesson) {
                    $isPublished = $trackable->is_published ?? false;
                    $progress = UserLessonProgress::where('user_id', $userId)
                        ->where('lesson_id', $trackable->id)
                        ->first();
                    $completed = $progress && (
                        ($progress->is_completed ?? false) ||
                        ($progress->status === 'completed') ||
                        (($progress->progress_percentage ?? 0) >= 100)
                    );
                } elseif ($trackable instanceof \App\Models\Scenarios\Scenario) {
                    $isPublished = $trackable->is_published ?? false;
                    $attempt = UserScenarioAttempt::where('user_id', $userId)
                        ->where('scenario_id', $trackable->id)
                        ->orderBy('started_at', 'desc')
                        ->first();
                    $completed = $attempt && (
                        ($attempt->is_completed ?? false) ||
                        ($attempt->status === 'finished') ||
                        (($attempt->progress_percentage ?? 0) >= 100)
                    );
                } else {
                    $completed = false;
                }

                if ($isPublished) {
                    $publishedCount++;
                    if ($completed) {
                        $completedCount++;
                    }
                    
                    $type = $trackable instanceof \App\Models\Learning\Lesson ? 'درس' : 'سيناريو';
                    $statusIcon = $completed ? '<fg=green>✓</>' : '<fg=red>✗</>';
                    $this->line("  {$statusIcon} {$type} ID: {$trackable->id} | order_index: {$track->order_index}");
                }
            }
            
            $this->line('');
            $this->line("  المنشورة: {$publishedCount} | المكتملة: {$completedCount} / {$publishedCount}");
        } else {
            $this->line("<fg=yellow>لا توجد عناصر في المستوى (فارغ)</>");
        }

        // الشهادة
        if ($certificate) {
            $this->line('');
            $this->line("<fg=green>✓ شهادة موجودة: ID {$certificate->id}</>");
        }
    }
}
