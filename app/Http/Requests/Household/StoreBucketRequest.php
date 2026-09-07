<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBucketRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('buckets', 'name')->where('household_id', $this->household()->id),
            ],
            'kind' => ['required', Rule::in(['goal', 'ongoing'])],
            'monthly_obligation' => ['required', 'integer', 'min:0'],
            'target_amount' => ['nullable', 'integer', 'min:0'],
            'target_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
