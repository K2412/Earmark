<?php

namespace App\Http\Requests\Household;

use App\Enums\PositionClassification;
use App\Enums\PositionPurpose;
use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialPositionRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'classification' => ['required', Rule::enum(PositionClassification::class)],
            'purpose' => ['required', Rule::enum(PositionPurpose::class)],
            'amount' => ['required', 'integer', 'min:0'],
            'valued_at' => ['required', 'date_format:Y-m-d'],
            'owner_user_id' => [
                'nullable',
                'integer',
                Rule::exists('household_members', 'user_id')->where('household_id', $this->household()->id),
            ],
        ];
    }
}
