<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
{
    use AuthorizesHousehold;

    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $transaction instanceof Transaction
            && ($this->user()?->can('view', $this->household()) ?? false)
            && $transaction->household_id === $this->household()->id
            && $transaction->transfer_pair_id === null;
    }

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
            'cleared' => ['required', 'boolean'],
            'reviewed' => ['required', 'boolean'],
        ];
    }
}
