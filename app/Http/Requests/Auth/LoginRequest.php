<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => strtolower(trim((string) $this->input('email'))),
            'device_name' => trim(
                (string) $this->input('device_name', 'task-manager-client')
            ),
        ]);
    }

    /**
     * قوانین اعتبارسنجی ورود.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
            ],

            'password' => [
                'required',
                'string',
                'max:255',
            ],

            'device_name' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }
}
