<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * مشخص می‌کند کاربر اجازه ارسال این درخواست را دارد.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * قبل از اعتبارسنجی، اطلاعات ورودی را تمیز می‌کند.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'device_name' => trim(
                (string) $this->input('device_name', 'task-manager-client')
            ),
        ]);
    }

    /**
     * قوانین اعتبارسنجی ثبت‌نام.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'string',
                'confirmed',
                'max:255',
                Password::min(8),
            ],

            'device_name' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }
}
