<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Splash screen: version check (force / optional update), maintenance flag, languages, support contacts.
 */
class AppConfigController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => ['nullable', 'in:android,ios'],
            'version' => ['nullable', 'string', 'regex:/^\d+(\.\d+){0,3}$/'],
        ]);

        $settings = Setting::public()->get()->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->typedValue()]);

        $platforms = collect(['android', 'ios'])->mapWithKeys(fn (string $platform) => [$platform => [
            'min_version' => $settings->get("{$platform}_min_version"),
            'latest_version' => $settings->get("{$platform}_latest_version"),
            'store_url' => $settings->get("{$platform}_store_url"),
        ]]);

        return $this->success([
            'update' => $this->updateStatus($platforms->get($request->input('platform')), $request->input('version')),
            'versions' => $platforms,
            'maintenance_mode' => (bool) $settings->get('maintenance_mode', false),
            'languages' => collect(config('app.supported_locales'))
                ->map(fn (array $locale, string $code) => ['code' => $code, ...$locale])
                ->values(),
            'default_language' => config('app.locale'),
            'otp' => [
                'length' => config('otp.length'),
                'expires_in' => config('otp.expires_seconds'),
                'resend_in' => config('otp.resend_seconds'),
            ],
            'support' => [
                'email' => $settings->get('support_email'),
                'phone' => $settings->get('support_phone'),
                'whatsapp' => $settings->get('support_whatsapp'),
            ],
        ]);
    }

    /**
     * "force" below min_version, "optional" below latest_version, otherwise "none".
     *
     * @param  array{min_version: ?string, latest_version: ?string, store_url: ?string}|null  $platform
     */
    private function updateStatus(?array $platform, ?string $version): string
    {
        if ($platform === null || $version === null) {
            return 'none';
        }

        return match (true) {
            $platform['min_version'] && version_compare($version, $platform['min_version'], '<') => 'force',
            $platform['latest_version'] && version_compare($version, $platform['latest_version'], '<') => 'optional',
            default => 'none',
        };
    }
}
