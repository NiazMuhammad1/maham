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

        $this->seedProviderSlide();
    }

    /**
     * "Start Earning with Maham" screen before Become a Service Provider (`?audience=provider`).
     * Image: database/seeders/images/onboarding/provider-1.png.
     */
    private function seedProviderSlide(): void
    {
        $slide = OnboardingSlide::updateOrCreate(
            ['audience' => 'provider', 'sort_order' => 1],
            [
                'title_en' => 'Start Earning with Maham',
                'title_ar' => 'ابدأ الكسب مع مهام',
                'description_en' => "Work how, when, and where you want\n\n"
                    ."Join the Maham platform as a verified service provider. Offer your skills in different categories from AC cleaning and repairs to plumbing, assembly, and logistics.\n\n"
                    .'You build your business, set your own rates, and receive 100% of your earnings securely through our app',
                'description_ar' => "اعمل بالطريقة والوقت والمكان الذي تريده\n\n"
                    ."انضم إلى منصة مهام كمقدم خدمة موثّق. قدّم مهاراتك في فئات مختلفة من تنظيف وإصلاح المكيفات إلى السباكة والتركيب والخدمات اللوجستية.\n\n"
                    .'أنت تبني عملك، وتحدد أسعارك، وتحصل على 100% من أرباحك بأمان عبر تطبيقنا',
            ],
        );

        $this->attachImage($slide, 'onboarding/provider-1.png', 'image');
    }
}
