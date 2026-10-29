<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    /**
     * Saudi banks for the "Select Bank" dropdown on Payout Settings (safe to run again on live).
     * Picking a bank also fills its SWIFT / BIC code in the app.
     */
    public function run(): void
    {
        $banks = [
            ['Saudi National Bank (SNB)', 'البنك الأهلي السعودي', 'NCBKSAJE'],
            ['Al Rajhi Bank', 'مصرف الراجحي', 'RJHISARI'],
            ['Riyad Bank', 'بنك الرياض', 'RIBLSARI'],
            ['Saudi Awwal Bank (SAB)', 'البنك السعودي الأول', 'SABBSARI'],
            ['Banque Saudi Fransi', 'البنك السعودي الفرنسي', 'BSFRSARI'],
            ['Arab National Bank', 'البنك العربي الوطني', 'ARNBSARI'],
            ['Alinma Bank', 'مصرف الإنماء', 'INMASARI'],
            ['Bank Albilad', 'بنك البلاد', 'ALBISARI'],
            ['Bank AlJazira', 'بنك الجزيرة', 'BJAZSAJE'],
            ['The Saudi Investment Bank', 'البنك السعودي للاستثمار', 'SIBCSARI'],
            ['Gulf International Bank', 'بنك الخليج الدولي', 'GULFSARI'],
        ];

        foreach ($banks as $index => [$nameEn, $nameAr, $swiftCode]) {
            Bank::updateOrCreate(['swift_code' => $swiftCode], [
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
