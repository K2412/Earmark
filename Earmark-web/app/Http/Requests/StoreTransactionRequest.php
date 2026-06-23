<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
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
            'date' => ['required', 'date_format:Y-m-d'],
            'account_id' => ['required', 'ulid', 'exists:accounts,id'],
            'payee' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'ulid', 'exists:categories,id'],
            'bucket_id' => ['nullable', 'ulid', 'exists:buckets,id'],
            'amount' => ['required', 'integer'],
            'memo' => ['nullable', 'string', 'max:1000'],
            'cleared' => ['boolean'],
        ];
    }
}
