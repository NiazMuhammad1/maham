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
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Controllers\Api\V1\LanguageController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OfferController;
use App\Http\Controllers\Api\V1\OnboardingSlideController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\Profile\PasswordController as ProfilePasswordController;
use App\Http\Controllers\Api\V1\Profile\PhoneController as ProfilePhoneController;
use App\Http\Controllers\Api\V1\Profile\ProfileController;
use App\Http\Controllers\Api\V1\Provider\ApplicationController as ProviderApplicationController;
use App\Http\Controllers\Api\V1\Provider\BookingController as ProviderBookingController;
use App\Http\Controllers\Api\V1\Provider\DashboardController as ProviderDashboardController;
use App\Http\Controllers\Api\V1\Provider\EarningsController as ProviderEarningsController;
use App\Http\Controllers\Api\V1\ProviderController;
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
        Route::post('service-requests/{serviceRequest}/cancel', [ServiceRequestController::class, 'cancel'])->name('service-requests.cancel');

        // Offers Received, Offer Details, Accept Offer (pay + book)
        Route::get('service-requests/{serviceRequest}/offers', [OfferController::class, 'index'])->name('service-requests.offers');
        Route::get('offers/{offer}', [OfferController::class, 'show'])->name('offers.show');
        Route::post('offers/{offer}/accept', [OfferController::class, 'accept'])->middleware('throttle:10,1')->name('offers.accept');

        // My Bookings, Booking Details (Booking Confirmed) and Rate Provider
        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        Route::post('bookings/{booking}/review', [BookingController::class, 'review'])->middleware('throttle:10,1')->name('bookings.review');

        // Provider: Start Work / Complete Work (moves the booking along the timeline) and Become a Service Provider
        Route::prefix('provider')->name('provider.')->group(function () {
            Route::post('bookings/{booking}/start', [ProviderBookingController::class, 'start'])->name('bookings.start');
            Route::post('bookings/{booking}/complete', [ProviderBookingController::class, 'complete'])->name('bookings.complete');

            // Become a Service Provider: Select Service -> Service Area -> Business Details -> Upload Logo (submit)
            Route::get('application', [ProviderApplicationController::class, 'show'])->name('application.show');
            Route::post('application/services', [ProviderApplicationController::class, 'services'])->name('application.services');
            Route::post('application/service-area', [ProviderApplicationController::class, 'serviceArea'])->name('application.service-area');
            Route::post('application/business-details', [ProviderApplicationController::class, 'businessDetails'])->name('application.business-details');
            Route::post('application/submit', [ProviderApplicationController::class, 'submit'])->middleware('throttle:10,1')->name('application.submit');

            // Provider Home (Dashboard) and Earnings (approved providers only)
            Route::get('dashboard', ProviderDashboardController::class)->name('dashboard');
            Route::get('earnings', ProviderEarningsController::class)->name('earnings');
        });

        // Chat
        Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::post('bookings/{booking}/conversation', [ConversationController::class, 'openFromBooking'])->name('bookings.conversation');
        Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
        Route::post('conversations/{conversation}/messages', [ConversationController::class, 'send'])->middleware('throttle:30,1')->name('conversations.messages.store');

        // Provider Details and Customers Reviews
        Route::get('providers/{provider}', [ProviderController::class, 'show'])->name('providers.show');
        Route::get('providers/{provider}/reviews', [ProviderController::class, 'reviews'])->name('providers.reviews');

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

        // Side menu
        Route::put('profile/language', [LanguageController::class, 'update'])->name('profile.language');
        Route::put('profile/mode', [ProfileController::class, 'updateMode'])->name('profile.mode');

        // Account Settings: Personal Details, Home Address, Notification Settings
        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('profile/personal-details', [ProfileController::class, 'updatePersonalDetails'])->name('profile.personal-details');
        Route::put('profile/address', [ProfileController::class, 'updateAddress'])->name('profile.address');
        Route::put('profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');

        // Account Settings: Update Password and Update Phone Number (both confirmed by OTP)
        Route::middleware('throttle:otp')->group(function () {
            Route::post('profile/password/send-otp', [ProfilePasswordController::class, 'sendOtp'])->name('profile.password.send-otp');
            Route::post('profile/password', [ProfilePasswordController::class, 'update'])->name('profile.password.update');
            Route::post('profile/phone/send-otp', [ProfilePhoneController::class, 'sendOtp'])->name('profile.phone.send-otp');
            Route::post('profile/phone/verify-otp', [ProfilePhoneController::class, 'verifyOtp'])->name('profile.phone.verify-otp');
        });
    });
});
