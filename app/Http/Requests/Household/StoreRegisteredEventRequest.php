<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use App\Models\RegisteredAccountEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegisteredEventRequest extends FormRequest
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
            'owner_user_id' => ['nullable', 'integer', Rule::exists('household_members', 'user_id')->where('household_id', $householdId)],
            'type' => ['required', Rule::in(RegisteredAccountEvent::TYPES)],
            'amount' => ['required', 'integer', 'min:0'],
            'occurred_on' => ['required', 'date_format:Y-m-d'],
            'plan_year' => ['required', 'integer', 'between:1990,2100'],
            'as_of' => ['nullable', 'required_if:type,room_override', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
