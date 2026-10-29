<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payments sheet -> Tabby / Tamara selected -> "Confirm and Pay": open the gateway's checkout page.
 */
class StartCheckoutRequest extends FormRequest
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
            // tabby | tamara (card / Apple Pay are paid with the Moyasar SDK instead)
            'payment_method' => ['required', Rule::in(Payment::INSTALLMENT_METHODS)],
        ];
    }
}
