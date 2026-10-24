<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Submit Review" on the Rate Provider sheet.
 */
class StoreReviewRequest extends FormRequest
{
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
            // "Give Ratings": 1 to 5 stars
            'rating' => ['required', 'integer', 'between:1,5'],
            // "Enter your review" (optional)
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
