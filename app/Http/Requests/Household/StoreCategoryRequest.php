<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
                Rule::unique('categories', 'name')->where('household_id', $this->household()->id),
            ],
            'type' => ['required', Rule::in(['income', 'housing', 'transportation', 'food', 'household', 'personal', 'health', 'debt', 'savings', 'fees', 'other'])],
        ];
    }
}
