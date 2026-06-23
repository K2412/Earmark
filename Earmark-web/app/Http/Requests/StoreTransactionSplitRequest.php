<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTransactionSplitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'splits' => ['required', 'array', 'min:1'],
            'splits.*.category_id' => ['nullable', 'ulid', 'exists:categories,id'],
            'splits.*.bucket_id' => ['required', 'ulid', 'exists:buckets,id'],
            'splits.*.amount' => ['required', 'integer'],
            'splits.*.memo' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $transaction = $this->route('transaction');
                $total = collect($this->input('splits', []))->sum('amount');

                if ($transaction && $total !== $transaction->amount) {
                    $validator->errors()->add('splits', 'Split amounts must equal the transaction amount.');
                }
            },
        ];
    }
}
