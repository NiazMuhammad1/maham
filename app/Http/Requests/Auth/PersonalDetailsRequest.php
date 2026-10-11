<?php

namespace App\Http\Requests\Auth;

use App\Rules\SaudiNationalId;
use Illuminate\Validation\Rule;

class PersonalDetailsRequest extends RegistrationStepRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => mb_strtolower(trim((string) $this->input('username'))),
            'national_id' => preg_replace('/\D/', '', (string) $this->input('national_id')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_.]+$/', Rule::unique('users')->ignore($this->user())],
            'national_id' => ['required', 'string', new SaudiNationalId, Rule::unique('users')->ignore($this->user())],
        ];
    }
}
