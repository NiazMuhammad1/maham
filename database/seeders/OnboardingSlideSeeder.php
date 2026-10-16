<?php

namespace Database\Seeders;

use App\Models\OnboardingSlide;
use Database\Seeders\Concerns\AttachesSeedImages;
use Illuminate\Database\Seeder;

class OnboardingSlideSeeder extends Seeder
{
    use AttachesSeedImages;

    /**
     * Customer onboarding slides from the design.
     *
     * Images are read from database/seeders/images/onboarding/ (skipped when the file is missing).
     */
    public function run(): void
    {
        $descriptionEn = 'All your services in one place. Cleaning, moving, repairs, delivery, beauty services, and more, all available with a tap';
        $descriptionAr = 'جميع خدماتك في مكان واحد. تنظيف، نقل، إصلاحات، توصيل، خدمات تجميل والمزيد، كلها متاحة بلمسة واحدة';

        $slides = [
            ['Access to Trusted Professionals', 'الوصول إلى محترفين موثوقين', 'onboarding/slide-1.png'],
            ['Instant and Seamless Booking', 'حجز فوري وسلس', 'onboarding/slide-2.png'],
            ['Work Autonomy and Flexibility', 'استقلالية ومرونة في العمل', 'onboarding/slide-3.png'],
            ['Work Autonomy and Flexibility', 'استقلالية ومرونة في العمل', 'onboarding/slide-4.png'],
        ];

        foreach ($slides as $index => [$titleEn, $titleAr, $image]) {
            $slide = OnboardingSlide::updateOrCreate(
                ['audience' => 'customer', 'sort_order' => $index + 1],
                [
                    'title_en' => $titleEn,
                    'title_ar' => $titleAr,
                    'description_en' => $descriptionEn,
                    'description_ar' => $descriptionAr,
                ],
            );

            $this->attachImage($slide, $image, 'image');
        }
    }
}
