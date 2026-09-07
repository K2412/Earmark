<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $householdId = $this->household()->id;

        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'from_account_id' => ['required', 'string', Rule::exists('accounts', 'id')->where('household_id', $householdId)],
            'to_account_id' => [
                'required',
                'string',
                'different:from_account_id',
                Rule::exists('accounts', 'id')->where('household_id', $householdId),
            ],
            'amount' => ['required', 'integer', 'min:1'],
            'memo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
