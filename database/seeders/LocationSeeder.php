<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Main Saudi cities with their popular districts. More can be added from the admin portal.
     */
    public function run(): void
    {
        $cities = [
            ['Riyadh', 'الرياض', [
                ['Al Olaya', 'العليا'], ['Al Malqa', 'الملقا'], ['Al Yasmin', 'الياسمين'], ['Al Narjis', 'النرجس'],
                ['Hittin', 'حطين'], ['Al Sahafa', 'الصحافة'], ['Al Nakheel', 'النخيل'], ['Al Wurud', 'الورود'],
                ['Al Rawdah', 'الروضة'], ['Al Sulimaniyah', 'السليمانية'], ['Al Muruj', 'المروج'], ['Qurtubah', 'قرطبة'],
                ['Al Aqiq', 'العقيق'], ['Al Rabwah', 'الربوة'], ['Al Malaz', 'الملز'], ['Al Naseem', 'النسيم'],
            ]],
            ['Jeddah', 'جدة', [
                ['Al Rawdah', 'الروضة'], ['Al Zahra', 'الزهراء'], ['Al Salamah', 'السلامة'], ['Al Shati', 'الشاطئ'],
                ['Al Hamra', 'الحمراء'], ['Al Naeem', 'النعيم'], ['Al Basateen', 'البساتين'], ['Al Muhammadiyah', 'المحمدية'],
                ['Obhur Al Shamaliyah', 'أبحر الشمالية'], ['Al Safa', 'الصفا'], ['Al Marwah', 'المروة'], ['Al Aziziyah', 'العزيزية'],
            ]],
            ['Makkah', 'مكة المكرمة', [
                ['Al Aziziyah', 'العزيزية'], ['Al Awali', 'العوالي'], ['Al Shawqiyah', 'الشوقية'], ['Al Naseem', 'النسيم'],
                ['Batha Quraish', 'بطحاء قريش'], ['Al Zaher', 'الزاهر'],
            ]],
            ['Madinah', 'المدينة المنورة', [
                ['Quba', 'قباء'], ['Al Aziziyah', 'العزيزية'], ['Al Khalidiyah', 'الخالدية'], ['Al Rawabi', 'الروابي'],
                ['Shuran', 'شوران'], ['Al Aqiq', 'العقيق'],
            ]],
            ['Dammam', 'الدمام', [
                ['Al Faisaliyah', 'الفيصلية'], ['Al Shati', 'الشاطئ'], ['Al Mazruiyah', 'المزروعية'], ['Al Nakheel', 'النخيل'],
                ['Al Hamra', 'الحمراء'], ['Al Noor', 'النور'],
            ]],
            ['Al Khobar', 'الخبر', [
                ['Al Aqrabiyah', 'العقربية'], ['Al Ulaya', 'العليا'], ['Al Rakah', 'الراكة'], ['Al Yarmouk', 'اليرموك'],
                ['Al Hizam Al Akhdar', 'الحزام الأخضر'], ['Al Thuqbah', 'الثقبة'],
            ]],
            ['Dhahran', 'الظهران', [['Doha', 'الدوحة'], ['Al Jamiyin', 'الجامعيين']]],
            ['Taif', 'الطائف', [['Al Hawiyah', 'الحوية'], ['Al Shifa', 'الشفا'], ['Shihar', 'شهار']]],
            ['Tabuk', 'تبوك', [['Al Muruj', 'المروج'], ['Al Faisaliyah', 'الفيصلية']]],
            ['Abha', 'أبها', [['Al Mansak', 'المنسك'], ['Al Khalidiyah', 'الخالدية']]],
            ['Khamis Mushait', 'خميس مشيط', [['Al Rasras', 'الرصراص'], ['Al Dhabab', 'الضباب']]],
            ['Buraidah', 'بريدة', [['Al Iskan', 'الإسكان'], ['Al Rayyan', 'الريان']]],
            ['Hail', 'حائل', [['Al Nuqrah', 'النقرة'], ['Al Muntazah', 'المنتزه']]],
            ['Al Ahsa', 'الأحساء', [['Al Mubarraz', 'المبرز'], ['Al Hofuf', 'الهفوف']]],
            ['Jubail', 'الجبيل', [['Al Fanateer', 'الفناتير'], ['Al Deffi', 'الدفي']]],
            ['Yanbu', 'ينبع', [['Al Nawa', 'النوى'], ['Al Sumairi', 'السميري']]],
            ['Najran', 'نجران', [['Al Fahd', 'الفهد'], ['Al Faisaliyah', 'الفيصلية']]],
            ['Jazan', 'جازان', [['Al Rawdah', 'الروضة'], ['Al Shati', 'الشاطئ']]],
        ];

        foreach ($cities as $cityIndex => [$nameEn, $nameAr, $districts]) {
            $city = City::updateOrCreate(['name_en' => $nameEn], ['name_ar' => $nameAr, 'sort_order' => $cityIndex]);

            foreach ($districts as [$districtEn, $districtAr]) {
                $city->districts()->updateOrCreate(['name_en' => $districtEn], ['name_ar' => $districtAr]);
            }
        }
    }
}
