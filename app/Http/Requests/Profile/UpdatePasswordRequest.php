<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * فقط کاربر احراز هویت‌شده اجازه تغییر رمز دارد.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * قوانین اعتبارسنجی تغییر رمز عبور.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => [
                'required',
                'string',
                'current_password:sanctum',
            ],

            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                'max:255',
                Password::min(8),
            ],
        ];
    }
}
