<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
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
