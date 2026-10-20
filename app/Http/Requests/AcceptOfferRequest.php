<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Confirm and Pay" on the Payments sheet of "Accept Offer".
 */
class AcceptOfferRequest extends FormRequest
{
    /**
     * Guests have to create an account before they can book.
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
            // "Select Payment Method": card | apple_pay
            'payment_method' => ['required', Rule::in(Payment::METHODS)],

            // Payment id returned by the Moyasar SDK after the customer paid.
            // Not needed while PAYMENT_DRIVER=fake (local / staging). A payment id can only be used once.
            'payment_id' => [
                Rule::requiredIf(config('services.payment.driver') !== 'fake'),
                'nullable',
                'string',
                'max:100',
                Rule::unique('payments', 'transaction_id'),
            ],
        ];
    }
}
