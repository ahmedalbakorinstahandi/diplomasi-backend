<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,

            // Users/RBAC
            UserSeeder::class,

            // Learning
            CourseSeeder::class,
            LevelSeeder::class,
            LessonSeeder::class,
            LessonQuestionSeeder::class,
            LessonQuestionOptionSeeder::class,
            LessonSummarySeeder::class,
            GlossaryTermSeeder::class,

            // Scenarios
            ScenarioSeeder::class,
            ScenarioQuestionSeeder::class,
            ScenarioQuestionOptionSeeder::class,
            LevelTrackSeeder::class,

            // Billing
            PlanSeeder::class,
            DiscountCouponSeeder::class,
            SubscriptionSeeder::class,

            // Progress
            UserCourseSeeder::class,
            UserLevelProgressSeeder::class,
            UserLessonProgressSeeder::class,
            UserLessonAttemptSeeder::class,
            UserScenarioAttemptSeeder::class,

            // Content
            ArticleSeeder::class,
            PageSeeder::class,
            FaqSeeder::class,

            // System
            SettingSeeder::class,
            NotificationSeeder::class,
            CertificateSeeder::class,
            ActivityLogSeeder::class,
        ]);
    }
}
