<?php

declare(strict_types=1);

namespace App\Http\Requests\PushDevice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnregisterPushDeviceRequest extends FormRequest
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
            'token' => [
                'required',
                'string',
                'max:512',
            ],

            'environment' => [
                'required',
                Rule::in([
                    'development',
                    'production',
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('token')) {
            $this->merge([
                'token' => trim(
                    (string) $this->input(
                        'token',
                    ),
                ),
            ]);
        }
    }
}
