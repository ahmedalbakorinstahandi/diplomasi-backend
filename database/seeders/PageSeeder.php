<?php

namespace Database\Seeders;

use App\Models\Content\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            ['slug' => 'about', 'title' => 'من نحن', 'content' => 'صفحة تعريفية تجريبية عن المنصة.', 'is_published' => true],
            ['slug' => 'terms', 'title' => 'الشروط والأحكام', 'content' => 'نص تجريبي للشروط والأحكام.', 'is_published' => true],
            ['slug' => 'privacy', 'title' => 'سياسة الخصوصية', 'content' => 'نص تجريبي لسياسة الخصوصية.', 'is_published' => true],
            ['slug' => 'contact', 'title' => 'تواصل معنا', 'content' => 'صفحة تواصل تجريبية مع البريد ورقم الهاتف.', 'is_published' => false],
        ];

        foreach ($pages as $page) {
            Page::withTrashed()->updateOrCreate(
                ['slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'content' => $page['content'],
                    'is_published' => $page['is_published'],
                    'deleted_at' => null,
                ]
            );
        }
    }
}

