<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignFundsRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $householdId = $this->household()->id;
        $bucket = Rule::exists('buckets', 'id')->where('household_id', $householdId);

        return [
            'from_bucket_id' => ['required', 'string', $bucket],
            'to_bucket_id' => ['required', 'string', 'different:from_bucket_id', $bucket],
            'amount' => ['required', 'integer', 'min:1'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'memo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
