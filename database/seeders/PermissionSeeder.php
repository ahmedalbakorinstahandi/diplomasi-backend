<?php

namespace Database\Seeders;

use App\Models\Users\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seed all dashboard permissions used in code.
     *
     * Naming: <entity>.<action>
     */
    public function run(): void
    {
        $permissions = [
            // Dashboard access
            ['name' => 'admin.access', 'description' => 'Access dashboard/admin area'],

            // RBAC
            ['name' => 'permission.view', 'description' => 'View permissions'],
            ['name' => 'role.view', 'description' => 'View roles'],
            ['name' => 'role.create', 'description' => 'Create roles'],
            ['name' => 'role.update', 'description' => 'Update roles'],
            ['name' => 'role.delete', 'description' => 'Delete roles'],
            ['name' => 'role.assign_permissions', 'description' => 'Assign permissions to roles'],

            // Users
            ['name' => 'user.view', 'description' => 'View users'],
            ['name' => 'user.create', 'description' => 'Create users'],
            ['name' => 'user.update', 'description' => 'Update users'],
            ['name' => 'user.delete', 'description' => 'Delete users'],

            // Courses
            ['name' => 'course.view', 'description' => 'View courses'],
            ['name' => 'course.create', 'description' => 'Create courses'],
            ['name' => 'course.update', 'description' => 'Update courses'],
            ['name' => 'course.delete', 'description' => 'Delete courses'],
            ['name' => 'course.reorder', 'description' => 'Reorder courses'],

            // Lessons
            ['name' => 'lesson.view', 'description' => 'View lessons'],
            ['name' => 'lesson.create', 'description' => 'Create lessons'],
            ['name' => 'lesson.update', 'description' => 'Update lessons'],
            ['name' => 'lesson.delete', 'description' => 'Delete lessons'],
            ['name' => 'lesson.reorder', 'description' => 'Reorder lessons'],

            // Levels
            ['name' => 'level.view', 'description' => 'View levels'],
            ['name' => 'level.create', 'description' => 'Create levels'],
            ['name' => 'level.update', 'description' => 'Update levels'],
            ['name' => 'level.delete', 'description' => 'Delete levels'],
            ['name' => 'level.reorder', 'description' => 'Reorder levels'],

            // // Level Tracks
            // ['name' => 'level_track.view', 'description' => 'View level tracks'],
            // ['name' => 'level_track.create', 'description' => 'Create level tracks'],
            // ['name' => 'level_track.update', 'description' => 'Update level tracks'],
            // ['name' => 'level_track.delete', 'description' => 'Delete level tracks'],
            // ['name' => 'level_track.reorder', 'description' => 'Reorder level tracks'],

            // Scenarios
            ['name' => 'scenario.view', 'description' => 'View scenarios'],
            ['name' => 'scenario.create', 'description' => 'Create scenarios'],
            ['name' => 'scenario.update', 'description' => 'Update scenarios'],
            ['name' => 'scenario.delete', 'description' => 'Delete scenarios'],
            ['name' => 'scenario.reorder', 'description' => 'Reorder scenarios'],

            // Progress (dashboard-only management if added later)
            ['name' => 'progress.view', 'description' => 'View progress'],
            ['name' => 'progress.create', 'description' => 'Create progress'],
            ['name' => 'progress.update', 'description' => 'Update progress'],
            ['name' => 'progress.delete', 'description' => 'Delete progress'],

            // Articles
            ['name' => 'article.view', 'description' => 'View articles'],
            ['name' => 'article.create', 'description' => 'Create articles'],
            ['name' => 'article.update', 'description' => 'Update articles'],
            ['name' => 'article.delete', 'description' => 'Delete articles'],
            ['name' => 'article.reorder', 'description' => 'Reorder articles'],

            // Subscriptions
            ['name' => 'subscription.view', 'description' => 'View subscriptions'],
            ['name' => 'subscription.create', 'description' => 'Create subscriptions'],
            ['name' => 'subscription.update', 'description' => 'Update subscriptions'],
            ['name' => 'subscription.delete', 'description' => 'Delete subscriptions'],
            ['name' => 'subscription.cancel', 'description' => 'Cancel subscriptions'],
            ['name' => 'subscription.renew', 'description' => 'Renew subscriptions'],

            // Notifications
            ['name' => 'notification.view', 'description' => 'View notifications'],
            ['name' => 'notification.create', 'description' => 'Create notifications'],
            ['name' => 'notification.update', 'description' => 'Update notifications'],
            ['name' => 'notification.delete', 'description' => 'Delete notifications'],
            ['name' => 'notification.mark_as_read', 'description' => 'Mark notification as read'],
            ['name' => 'notification.mark_all_as_read', 'description' => 'Mark all notifications as read'],
            ['name' => 'notification.unread_count', 'description' => 'Get unread notifications count'],

            // Re-engagement reminders
            ['name' => 'reengagement_reminder.view', 'description' => 'View re-engagement reminders'],
            ['name' => 'reengagement_reminder.create', 'description' => 'Create re-engagement reminders'],
            ['name' => 'reengagement_reminder.update', 'description' => 'Update re-engagement reminders'],
            ['name' => 'reengagement_reminder.delete', 'description' => 'Delete re-engagement reminders'],
            ['name' => 'reengagement_reminder.reorder', 'description' => 'Reorder re-engagement reminders'],

            // Settings
            ['name' => 'setting.view', 'description' => 'View settings'],
            ['name' => 'setting.create', 'description' => 'Create settings'],
            ['name' => 'setting.update', 'description' => 'Update settings'],
            ['name' => 'setting.delete', 'description' => 'Delete settings'],
            ['name' => 'setting.update_many', 'description' => 'Update many settings'],

            // Faqs
            ['name' => 'faq.view', 'description' => 'View faqs'],
            ['name' => 'faq.create', 'description' => 'Create faqs'],
            ['name' => 'faq.update', 'description' => 'Update faqs'],
            ['name' => 'faq.delete', 'description' => 'Delete faqs'],
            ['name' => 'faq.reorder', 'description' => 'Reorder faqs'],

            // Glossary Terms
            ['name' => 'glossary_term.view', 'description' => 'View glossary terms'],
            ['name' => 'glossary_term.create', 'description' => 'Create glossary terms'],
            ['name' => 'glossary_term.update', 'description' => 'Update glossary terms'],
            ['name' => 'glossary_term.delete', 'description' => 'Delete glossary terms'],
            ['name' => 'glossary_term.reorder', 'description' => 'Reorder glossary terms'],

            // Plans
            ['name' => 'plan.view', 'description' => 'View plans'],
            ['name' => 'plan.create', 'description' => 'Create plans'],
            ['name' => 'plan.update', 'description' => 'Update plans'],
            ['name' => 'plan.delete', 'description' => 'Delete plans'],
            ['name' => 'plan.reorder', 'description' => 'Reorder plans'],

            // Certificates (dashboard)
            ['name' => 'certificate.view', 'description' => 'View certificates'],
            ['name' => 'certificate.issue', 'description' => 'Issue and regenerate certificates'],
            ['name' => 'certificate.revoke', 'description' => 'Revoke certificates'],
        ];

        // php artisan db:seed --class=PermissionSeeder
        // php artisan db:seed --class=RolePermissionSeeder

        // ssh root@76.13.143.214 'cd /; cd /home/diplomasi-backend/htdocs/backend.diplomasi.app; git pull; php artisan db:seed --class=PermissionSeeder; php artisan db:seed --class=RolePermissionSeeder;'

        foreach ($permissions as $perm) {
            Permission::withTrashed()->updateOrCreate(
                ['name' => $perm['name']],
                [
                    'description' => $perm['description'] ?? null,
                    'deleted_at' => null,
                ]
            );
        }
    }
}
