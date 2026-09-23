<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use App\Models\Transaction;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SplitTransactionRequest extends FormRequest
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
            'splits' => ['present', 'array'],
            'splits.*.category_id' => ['nullable', 'string', Rule::exists('categories', 'id')->where('household_id', $householdId)],
            'splits.*.bucket_id' => ['required', 'string', Rule::exists('buckets', 'id')->where('household_id', $householdId)],
            'splits.*.amount' => ['required', 'integer'],
            'splits.*.memo' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $splits = $this->input('splits', []);

            if ($splits === []) {
                return;
            }

            $transaction = $this->route('transaction');
            $total = array_sum(array_map(static fn ($split): int => (int) ($split['amount'] ?? 0), $splits));

            if ($transaction instanceof Transaction && $total !== $transaction->amount) {
                $validator->errors()->add('splits', 'Splits must sum exactly to the transaction amount.');
            }
        });
    }
}
