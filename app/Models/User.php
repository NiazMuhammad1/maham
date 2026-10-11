<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'username',
    'email',
    'password',
    'phone_country_code',
    'phone',
    'national_id',
    'avatar',
    'language',
    'is_guest',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const string STEP_VERIFY_PHONE = 'verify_phone';

    public const string STEP_PERSONAL_DETAILS = 'personal_details';

    public const string STEP_CREDENTIALS = 'credentials';

    public const string STEP_VERIFY_EMAIL = 'verify_email';

    public const string STEP_ADDRESS = 'address';

    public const string STEP_PROFILE_IMAGE = 'profile_image';

    public const string STEP_COMPLETED = 'completed';

    public const string STEP_GUEST = 'guest';

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

    public function avatarUrl(): ?string
    {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
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
