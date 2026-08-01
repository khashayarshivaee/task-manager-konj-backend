<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUsersRequest extends FormRequest
{
    /**
     * دسترسی اصلی ادمین توسط Middleware کنترل می‌شود.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * تمیزکردن فیلترهای ورودی قبل از اعتبارسنجی.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => trim((string) $this->input('search')),
            'role' => trim((string) $this->input('role')),
            'sort' => trim((string) $this->input('sort', 'latest')),
        ]);
    }

    /**
     * قوانین فیلتر و صفحه‌بندی کاربران.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'role' => [
                'nullable',
                Rule::enum(UserRole::class),
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'sort' => [
                'nullable',
                Rule::in([
                    'latest',
                    'oldest',
                    'name_asc',
                    'name_desc',
                ]),
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
