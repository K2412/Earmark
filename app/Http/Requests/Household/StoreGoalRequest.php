<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use App\Models\Goal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoalRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $householdId = $this->household()->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Goal::TYPES)],
            'target_amount' => ['required', 'integer', 'min:0'],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
            'apr_bps' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'required_payment' => ['nullable', 'integer', 'min:0'],
            'principal' => ['nullable', 'integer'],
            'linked_account_id' => ['nullable', 'string', Rule::exists('accounts', 'id')->where('household_id', $householdId)],
            'linked_bucket_id' => ['nullable', 'string', Rule::exists('buckets', 'id')->where('household_id', $householdId)],
            'linked_position_id' => ['nullable', 'string', Rule::exists('financial_positions', 'id')->where('household_id', $householdId)],
            'status' => ['nullable', Rule::in(['active', 'achieved', 'archived'])],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
