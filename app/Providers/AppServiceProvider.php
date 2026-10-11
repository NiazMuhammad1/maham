<?php

namespace App\Providers;

use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use App\Services\Sms\TaqnyatSmsSender;
use App\Services\Sms\UnifonicSmsSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsSender::class, fn () => match (config('services.sms.driver')) {
            'taqnyat' => new TaqnyatSmsSender(
                (string) config('services.sms.taqnyat.token'),
                (string) config('services.sms.taqnyat.sender'),
            ),
            'unifonic' => new UnifonicSmsSender(
                (string) config('services.sms.unifonic.app_sid'),
                (string) config('services.sms.unifonic.sender'),
            ),
            default => new LogSmsSender,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('otp', fn (Request $request) => [
            Limit::perMinute(5)->by('otp-min|'.$request->ip().'|'.$request->input('phone', $request->user()?->id)),
            Limit::perHour(20)->by('otp-hour|'.$request->ip()),
        ]);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)
            ->by('login|'.mb_strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
