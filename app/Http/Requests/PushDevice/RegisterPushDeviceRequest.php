<?php

declare(strict_types=1);

namespace App\Http\Requests\PushDevice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterPushDeviceRequest extends FormRequest
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

            'platform' => [
                'required',
                Rule::in([
                    'ios',
                ]),
            ],

            'environment' => [
                'required',
                Rule::in([
                    'development',
                    'production',
                ]),
            ],

            'device_name' => [
                'nullable',
                'string',
                'max:120',
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

        if ($this->has('device_name')) {
            $deviceName = trim(
                (string) $this->input(
                    'device_name',
                ),
            );

            $this->merge([
                'device_name' =>
                    $deviceName !== ''
                        ? $deviceName
                        : null,
            ]);
        }
    }
}
