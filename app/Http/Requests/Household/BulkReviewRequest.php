<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkReviewRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $householdId = $this->household()->id;

        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string', Rule::exists('transactions', 'id')->where('household_id', $householdId)],
            'reviewed' => ['required', 'boolean'],
        ];
    }
}
