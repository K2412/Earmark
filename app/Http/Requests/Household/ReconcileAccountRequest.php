<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReconcileAccountRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $householdId = $this->household()->id;

        return [
            'account_id' => ['required', 'string', Rule::exists('accounts', 'id')->where('household_id', $householdId)],
            'statement_date' => ['required', 'date_format:Y-m-d'],
            'statement_balance' => ['required', 'integer'],
        ];
    }
}
