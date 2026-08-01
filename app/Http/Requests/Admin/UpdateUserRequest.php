<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * دسترسی ادمین توسط Middleware کنترل می‌شود.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * تمیزکردن اطلاعات قبل از اعتبارسنجی.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'role' => strtolower(trim((string) $this->input('role'))),
        ]);
    }

    /**
     * قوانین ویرایش کاربر توسط ادمین.
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
                Rule::unique('users', 'email')
                    ->ignore($this->route('user')),
            ],

            'role' => [
                'required',
                Rule::enum(UserRole::class),
            ],

            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }
}
