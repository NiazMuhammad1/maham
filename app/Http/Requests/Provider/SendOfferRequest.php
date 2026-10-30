<?php

namespace App\Http\Requests\Provider;

use App\Models\Offer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Send Offer" on a customer's request (provider side).
 */
class SendOfferRequest extends FormRequest
{
    /**
     * Only approved providers (checked in the controller).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // "Execution date & time": customer_time = Agree with Customer's Time, proposed = Propose a Different Date & Time
            'schedule_type' => ['required', Rule::in(Offer::SCHEDULE_TYPES)],

            // "Select Date" / "Select Time": only used (and required) with schedule_type = proposed
            'proposed_date' => ['exclude_unless:schedule_type,proposed', 'required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'proposed_time' => ['exclude_unless:schedule_type,proposed', 'required', 'date_format:H:i'],

            // "Work description"
            'description' => ['required', 'string', 'max:2000'],

            // "Your proposed price" in SAR
            'price' => ['required', 'numeric', 'min:1', 'max:999999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proposed_date.after_or_equal' => __('messages.request_date_past'),
        ];
    }
}
