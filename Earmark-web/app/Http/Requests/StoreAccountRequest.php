<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['chequing', 'savings', 'credit_card', 'cash', 'investment', 'other'])],
            'starting_balance' => ['required', 'integer'],
            'starting_balance_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
