<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Placeholder legal pages; the real text is managed from the admin portal.
     */
    public function run(): void
    {
        $pages = [
            ['terms-of-service', 'Terms of Service', 'شروط الخدمة'],
            ['privacy-policy', 'Privacy Policy', 'سياسة الخصوصية'],
            ['about-us', 'About Maham', 'عن مهام'],
        ];

        foreach ($pages as [$slug, $titleEn, $titleAr]) {
            Page::firstOrCreate(['slug' => $slug], [
                'title_en' => $titleEn,
                'title_ar' => $titleAr,
                'content_en' => "<p>{$titleEn} content will be published soon.</p>",
                'content_ar' => "<p>سيتم نشر محتوى {$titleAr} قريباً.</p>",
            ]);
        }
    }
}
