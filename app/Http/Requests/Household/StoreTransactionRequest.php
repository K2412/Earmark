<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
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
            'account_id' => ['required', 'string', Rule::exists('accounts', 'id')->where('household_id', $householdId)],
            'payee' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'string', Rule::exists('categories', 'id')->where('household_id', $householdId)],
            'bucket_id' => ['nullable', 'string', Rule::exists('buckets', 'id')->where('household_id', $householdId)],
            'amount' => ['required', 'integer'],
            'memo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
