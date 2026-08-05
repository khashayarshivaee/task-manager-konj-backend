<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProjectTaskCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => trim(
                (string) $this->input(
                    'from',
                    ''
                )
            ),

            'to' => trim(
                (string) $this->input(
                    'to',
                    ''
                )
            ),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'from' => [
                'required',
                'date_format:Y-m-d',
            ],

            'to' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:from',
            ],
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                if (
                    $validator
                        ->errors()
                        ->has('from') ||
                    $validator
                        ->errors()
                        ->has('to')
                ) {
                    return;
                }

                $from =
                    CarbonImmutable::createFromFormat(
                        'Y-m-d',
                        (string) $this->input(
                            'from'
                        )
                    );

                $to =
                    CarbonImmutable::createFromFormat(
                        'Y-m-d',
                        (string) $this->input(
                            'to'
                        )
                    );

                if (
                    $from->diffInDays($to) > 62
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'to',
                            'The calendar range may not exceed 62 days.'
                        );
                }
            }
        );
    }
}
