<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Public app settings returned by GET /api/v1/app/config.
     */
    public function run(): void
    {
        $settings = [
            ['app', 'android_min_version', '1.0.0', 'string'],
            ['app', 'android_latest_version', '1.0.1', 'string'],
            ['app', 'android_store_url', 'https://play.google.com/store/apps/details?id=sa.maham.app', 'string'],
            ['app', 'ios_min_version', '1.0.0', 'string'],
            ['app', 'ios_latest_version', '1.0.1', 'string'],
            ['app', 'ios_store_url', 'https://apps.apple.com/app/maham', 'string'],
            ['app', 'maintenance_mode', '0', 'boolean'],
            ['support', 'support_email', 'support@maham.sa', 'string'],
            ['support', 'support_phone', '+966500000000', 'string'],
            ['support', 'support_whatsapp', '+966500000000', 'string'],
        ];

        foreach ($settings as [$group, $key, $value, $type]) {
            Setting::updateOrCreate(['key' => $key], [
                'group' => $group,
                'value' => $value,
                'type' => $type,
                'is_public' => true,
            ]);
        }
    }
}
