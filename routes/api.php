<?php

use App\Http\Controllers\Api\V1\AppConfigController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\GuestLoginController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\PhoneVerificationController;
use App\Http\Controllers\Api\V1\Auth\RegisterPhoneController;
use App\Http\Controllers\Api\V1\Auth\RegistrationController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Auth\SocialLoginController;
use App\Http\Controllers\Api\V1\Auth\UsernameAvailabilityController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\LanguageController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OnboardingSlideController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\ServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Splash, language, onboarding and lookups
    Route::get('app/config', AppConfigController::class)->name('app.config');
    Route::get('onboarding-slides', [OnboardingSlideController::class, 'index'])->name('onboarding-slides.index');
    Route::get('pages/{slug}', [PageController::class, 'show'])->name('pages.show');
    Route::get('cities', [CityController::class, 'index'])->name('cities.index');
    Route::get('cities/{city}/districts', [CityController::class, 'districts'])->name('cities.districts');

    Route::prefix('auth')->name('auth.')->group(function () {
        // Registration step 1: phone + OTP (returns the token used for the next steps)
        Route::middleware('throttle:otp')->group(function () {
            Route::post('register/send-otp', [RegisterPhoneController::class, 'sendOtp'])->name('register.send-otp');
            Route::post('register/verify-otp', [RegisterPhoneController::class, 'verifyOtp'])->name('register.verify-otp');

            Route::post('forgot-password/send-otp', [ForgotPasswordController::class, 'sendOtp'])->name('forgot-password.send-otp');
            Route::post('forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp'])->name('forgot-password.verify-otp');
        });
        Route::post('forgot-password/reset', [ForgotPasswordController::class, 'reset'])->middleware('throttle:login')->name('forgot-password.reset');

        Route::get('username-availability', UsernameAvailabilityController::class)->middleware('throttle:60,1')->name('username-availability');

        Route::post('login', LoginController::class)->middleware('throttle:login')->name('login');
        Route::post('social', SocialLoginController::class)->middleware('throttle:login')->name('social');
        Route::post('guest', GuestLoginController::class)->middleware('throttle:login')->name('guest');

        Route::middleware('auth:sanctum')->group(function () {
            // Registration steps 2-6
            Route::post('register/personal-details', [RegistrationController::class, 'personalDetails'])->name('register.personal-details');
            Route::post('register/credentials', [RegistrationController::class, 'credentials'])->name('register.credentials');
            Route::post('register/address', [RegistrationController::class, 'address'])->name('register.address');
            Route::post('register/complete', [RegistrationController::class, 'complete'])->name('register.complete');

            Route::middleware('throttle:otp')->group(function () {
                Route::post('email/send-otp', [EmailVerificationController::class, 'send'])->name('email.send-otp');
                Route::post('email/verify-otp', [EmailVerificationController::class, 'verify'])->name('email.verify-otp');
                Route::post('phone/send-otp', [PhoneVerificationController::class, 'send'])->name('phone.send-otp');
                Route::post('phone/verify-otp', [PhoneVerificationController::class, 'verify'])->name('phone.verify-otp');
            });

            Route::get('me', [SessionController::class, 'show'])->name('me');
            Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
            Route::post('change-password', [PasswordController::class, 'update'])->name('change-password');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('device-tokens', [DeviceTokenController::class, 'store'])->name('device-tokens.store');

        // Home, All Services, Service Details
        Route::get('home', HomeController::class)->name('home');
        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');

        // Request Service (customer) and My Requests
        Route::get('service-requests/options', [ServiceRequestController::class, 'options'])->name('service-requests.options');
        Route::get('service-requests', [ServiceRequestController::class, 'index'])->name('service-requests.index');
        Route::post('service-requests', [ServiceRequestController::class, 'store'])->name('service-requests.store');
        Route::get('service-requests/{serviceRequest}', [ServiceRequestController::class, 'show'])->name('service-requests.show');

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

        // Side menu
        Route::put('profile/language', [LanguageController::class, 'update'])->name('profile.language');
    });
});
