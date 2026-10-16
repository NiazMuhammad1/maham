<?php

namespace Database\Seeders;

use App\Models\Service;
use Database\Seeders\Concerns\AttachesSeedImages;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    use AttachesSeedImages;

    /**
     * Services from the Home / All Services design.
     *
     * Icons are read from database/seeders/images/services/{slug}.png (skipped when the file is missing).
     */
    public function run(): void
    {
        // [slug, name_en, name_ar, icon background, popular on Home]
        $services = [
            ['ac-repair', 'AC Repair', 'تصليح التكييف', '#B8EBD0', true],
            ['cleaning', 'Cleaning', 'التنظيف', '#B3E0F7', true],
            ['plumbing', 'Plumbing', 'السباكة', '#C9EBA5', true],
            ['salon', 'Salon', 'صالون', '#F5A8E6', true],
            ['electronics', 'Electronics', 'الإلكترونيات', '#FFB894', true],
            ['shifting', 'Shifting', 'الانتقال', '#B8EBD0', true],
            ['beauty', 'Beauty', 'الجمال', '#C9EBA5', false],
            ['appliance-repair', 'Appliance Repair', 'تصليح الأجهزة', '#FFD98F', false],
            ['painting', 'Painting', 'الدهان', '#F5A8E6', false],
        ];

        foreach ($services as $index => [$slug, $nameEn, $nameAr, $color, $isPopular]) {
            $service = Service::updateOrCreate(['slug' => $slug], [
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
                'icon_bg_color' => $color,
                'is_popular' => $isPopular,
                'sort_order' => $index + 1,
            ]);

            $this->attachImage($service, "services/{$slug}.png", 'icon');
        }

        $this->seedAcRepairDetails();
    }

    /**
     * Description and sub-services shown on the AC Repair "Service Details" design.
     */
    private function seedAcRepairDetails(): void
    {
        $service = Service::firstWhere('slug', 'ac-repair');

        $this->attachImage($service, 'services/ac-repair-cover.jpg', 'image');

        $service->update([
            'description_en' => 'Keep your home cool and comfortable. Describe the issue with your AC unit (e.g., not cooling, leaking, or noisy), upload photos for a faster diagnosis, and pick a time that works for you. Your request will be sent to verified HVAC specialists in your area.',
            'description_ar' => 'حافظ على برودة وراحة منزلك. قم بوصف مشكلة المكيف (مثل عدم التبريد أو التسريب أو الضوضاء)، وحمّل الصور لتشخيص أسرع، واختر الوقت المناسب لك. سيتم إرسال طلبك إلى متخصصي تكييف معتمدين في منطقتك.',
        ]);

        // [name_en, name_ar, description_en, description_ar]
        $subServices = [
            ['General Repair', 'تصليح عام', 'Troubleshooting common issues like units not cooling, leaking water, or making unusual noises.', 'استكشاف المشكلات الشائعة مثل عدم تبريد الوحدة، تسرب المياه، أو إصدار أصوات غير معتادة.'],
            ['Gas/Freon Refill', 'إعادة تعبئة الغاز/الفريون', 'A standard maintenance requirement for AC systems in high-heat environments like Saudi Arabia.', 'متطلب صيانة قياسي لأنظمة التكييف في البيئات الحارة مثل السعودية.'],
            ['Water Tank Cleaning', 'تنظيف فلاتر ومجرى مياه التكييف', "This is often bundled with AC cleaning in the platform's initial category list.", 'غالبًا ما يتم تجميعه مع تنظيف التكييف في قائمة الفئات الأولية للمنصة.'],
        ];

        foreach ($subServices as $index => [$nameEn, $nameAr, $descriptionEn, $descriptionAr]) {
            $service->subServices()->updateOrCreate(['name_en' => $nameEn], [
                'name_ar' => $nameAr,
                'description_en' => $descriptionEn,
                'description_ar' => $descriptionAr,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
