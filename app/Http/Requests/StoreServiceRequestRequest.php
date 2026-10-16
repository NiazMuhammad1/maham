<?php

namespace App\Http\Requests;

use App\Models\ServiceRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Submit Request" on the Confirm Request screen.
 *
 * The app collects the 4 "Request service" steps on the phone and sends everything here
 * in one multipart/form-data request.
 */
class StoreServiceRequestRequest extends FormRequest
{
    /**
     * Guests have to create an account before they can post a request.
     */
    public function authorize(): bool
    {
        return ! $this->user()->is_guest;
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('messages.guest_not_allowed'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        
        return [
            // The service opened on "Service Details" (e.g. AC Repair)
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)->whereNull('deleted_at')],

            // Step 1 - Add Address (address_id is optional: send it when the user kept one of their saved addresses)
            'address_id' => ['nullable', 'integer', Rule::exists('addresses', 'id')->where('user_id', $this->user()->id)],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('is_active', true)],
            'district_id' => [
                'required',
                'integer',
                Rule::exists('districts', 'id')->where('is_active', true)->where('city_id', $this->integer('city_id')),
            ],
            'street_address' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'regex:/^\d{5}$/'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            // Step 2 - Select Date and Time
            'preferred_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'preferred_time' => ['required', 'date_format:H:i'],
            'flexibility' => ['required', Rule::in(ServiceRequest::FLEXIBILITIES)],

            // Step 3 - What do you need done?
            'description' => ['required', 'string', 'max:2000'],
            'budget' => ['nullable', 'numeric', 'min:1', 'max:999999'],

            // Step 4 - Add photos (optional), up to 6 images of 5 MB each
            'images' => ['nullable', 'array', 'max:'.ServiceRequest::MAX_IMAGES],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'preferred_date.after_or_equal' => __('messages.request_date_past'),
        ];
    }
}
