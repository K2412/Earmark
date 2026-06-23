<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBucketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:buckets,name'],
            'kind' => ['required', Rule::in(['goal', 'ongoing'])],
            'monthly_obligation' => ['required', 'integer', 'min:0'],
            'target_amount' => ['nullable', 'integer', 'min:0'],
            'target_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
