<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'name',
    'username',
    'email',
    'password',
    'phone_country_code',
    'phone',
    'national_id',
    'language',
    'is_guest',
    'push_notifications',
    'email_notifications',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, InteractsWithMedia, Notifiable, SoftDeletes;

    public const string STEP_VERIFY_PHONE = 'verify_phone';

    public const string STEP_PERSONAL_DETAILS = 'personal_details';

    public const string STEP_CREDENTIALS = 'credentials';

    public const string STEP_VERIFY_EMAIL = 'verify_email';

    public const string STEP_ADDRESS = 'address';

    public const string STEP_PROFILE_IMAGE = 'profile_image';

    public const string STEP_COMPLETED = 'completed';

    public const string STEP_GUEST = 'guest';

    // Side menu "Customer Mode" / "Provider Mode" (`active_mode`); also which notifications list is shown
    public const string MODE_CUSTOMER = 'customer';

    public const string MODE_PROVIDER = 'provider';

    // "Become a Service Provider" application: status and next screen
    public const string APPLICATION_NOT_STARTED = 'not_started';

    public const string APPLICATION_DRAFT = 'draft';

    public const string APPLICATION_STEP_SERVICES = 'services';

    public const string APPLICATION_STEP_SERVICE_AREA = 'service_area';

    public const string APPLICATION_STEP_BUSINESS_DETAILS = 'business_details';

    public const string APPLICATION_STEP_LOGO = 'logo';

    public const string APPLICATION_STEP_SUBMITTED = 'submitted';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'registration_completed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_guest' => 'boolean',
            'is_provider' => 'boolean',
            'push_notifications' => 'boolean',
            'email_notifications' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * @return HasOne<Address, $this>
     */
    public function defaultAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }

    /**
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * @return HasMany<DeviceToken, $this>
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function fullPhone(): ?string
    {
        return $this->phone ? $this->phone_country_code.$this->phone : null;
    }

    /**
     * Profile photo (one file, a new upload replaces the old one).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
    }

    /**
     * @return HasMany<ServiceRequest, $this>
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'customer_id');
    }

    /**
     * Business profile, only for service providers.
     *
     * @return HasOne<ProviderProfile, $this>
     */
    public function providerProfile(): HasOne
    {
        return $this->hasOne(ProviderProfile::class);
    }

    /**
     * Services a provider works in ("Services" on Provider Details).
     *
     * @return BelongsToMany<Service, $this>
     */
    public function providedServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'provider_services', 'provider_id')->withTimestamps();
    }

    /**
     * Cities / districts a provider works in ("Choose Service Area").
     *
     * @return HasMany<ProviderServiceArea, $this>
     */
    public function serviceAreas(): HasMany
    {
        return $this->hasMany(ProviderServiceArea::class, 'provider_id');
    }

    /**
     * Where the "Become a Service Provider" application is:
     * not_started, draft, pending (waiting for admin review), approved, rejected or suspended.
     */
    public function providerApplicationStatus(): string
    {
        $profile = $this->providerProfile;

        return match (true) {
            $profile === null => self::APPLICATION_NOT_STARTED,
            $profile->status === ProviderProfile::STATUS_PENDING && $profile->submitted_at === null => self::APPLICATION_DRAFT,
            default => $profile->status,
        };
    }

    /**
     * The "Become a Service Provider" screen the app should open next, so an interrupted application can be resumed.
     */
    public function nextProviderApplicationStep(): string
    {
        $profile = $this->providerProfile;

        if ($profile !== null && ! $profile->isEditable()) {
            return self::APPLICATION_STEP_SUBMITTED;
        }

        return match (true) {
            ! $this->providedServices()->exists() => self::APPLICATION_STEP_SERVICES,
            ! $this->serviceAreas()->exists() => self::APPLICATION_STEP_SERVICE_AREA,
            blank($profile?->business_name) => self::APPLICATION_STEP_BUSINESS_DETAILS,
            default => self::APPLICATION_STEP_LOGO,
        };
    }

    /**
     * Reviews customers left for this provider.
     *
     * @return HasMany<Review, $this>
     */
    public function receivedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'provider_id');
    }

    /**
     * Bookings this user made as a customer.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    /**
     * Bookings this user works on as a provider.
     *
     * @return HasMany<Booking, $this>
     */
    public function providerBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'provider_id');
    }

    /**
     * A provider whose application was approved by the admin team (can use the provider screens).
     */
    public function isApprovedProvider(): bool
    {
        return $this->is_provider && $this->providerProfile?->status === ProviderProfile::STATUS_APPROVED;
    }

    /**
     * Save an in-app notification. The title and body are translated when the list is read
     * (see lang/{en,ar}/notifications.php), so only the type key and its :params are stored.
     *
     * A param can also be ['en' => ..., 'ar' => ...] (e.g. a service name or a date), so the reader sees it
     * in their own language even when the notification was created by someone using the other language.
     *
     * @param  array<string, string|array{en: string, ar: string}>  $params
     * @param  array<string, mixed>  $extra  e.g. ['booking_id' => 5] so the app knows which screen to open
     */
    public function addAppNotification(string $type, array $params, array $extra = []): void
    {
        $this->notifications()->create([
            'id' => Str::uuid()->toString(),
            'type' => $type,
            'data' => ['params' => $params, ...$extra],
        ]);
    }

    /**
     * A notification for the provider side ("Notifications" in Provider Mode).
     *
     * @param  array<string, string|array{en: string, ar: string}>  $params
     * @param  array<string, mixed>  $extra
     */
    public function addProviderNotification(string $type, array $params, array $extra = []): void
    {
        $this->addAppNotification($type, $params, [...$extra, 'mode' => self::MODE_PROVIDER]);
    }

    /**
     * Notifications of one side of the app: provider notifications are saved with data.mode = provider,
     * customer notifications have no mode.
     *
     * @return MorphMany<DatabaseNotification, $this>
     */
    public function notificationsFor(string $mode): MorphMany
    {
        return $mode === self::MODE_PROVIDER
            ? $this->notifications()->where('data->mode', self::MODE_PROVIDER)
            : $this->notifications()->whereNull('data->mode');
    }

    public function avatarUrl(): ?string
    {
        return $this->getFirstMediaUrl('avatar') ?: null;
    }

    /**
     * The registration screen the app should open next, so an interrupted sign-up can be resumed.
     */
    public function nextRegistrationStep(): string
    {
        return match (true) {
            $this->is_guest => self::STEP_GUEST,
            $this->phone_verified_at === null => self::STEP_VERIFY_PHONE,
            blank($this->name) || blank($this->username) || blank($this->national_id) => self::STEP_PERSONAL_DETAILS,
            blank($this->email) || ($this->password === null && ! $this->socialAccounts()->exists()) => self::STEP_CREDENTIALS,
            $this->email_verified_at === null => self::STEP_VERIFY_EMAIL,
            ! $this->addresses()->exists() => self::STEP_ADDRESS,
            $this->registration_completed_at === null => self::STEP_PROFILE_IMAGE,
            default => self::STEP_COMPLETED,
        };
    }
}
