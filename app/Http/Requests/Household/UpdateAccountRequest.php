<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use App\Models\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    use AuthorizesHousehold;

    public function authorize(): bool
    {
        $account = $this->route('account');

        return $account instanceof Account
            && ($this->user()?->can('view', $this->household()) ?? false)
            && $account->household_id === $this->household()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $householdId = $this->household()->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Account::TYPES))],
            'owner_user_id' => ['nullable', 'integer', Rule::exists('household_members', 'user_id')->where('household_id', $householdId)],
            'starting_balance' => ['required', 'integer'],
            'starting_balance_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
