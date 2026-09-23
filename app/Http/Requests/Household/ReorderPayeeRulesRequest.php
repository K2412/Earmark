<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderPayeeRulesRequest extends FormRequest
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
            'ids.*' => ['string', Rule::exists('payee_rules', 'id')->where('household_id', $householdId)],
        ];
    }
}
