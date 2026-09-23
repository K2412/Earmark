<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use App\Models\Transaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignReviewRequest extends FormRequest
{
    use AuthorizesHousehold;

    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $transaction instanceof Transaction
            && ($this->user()?->can('view', $this->household()) ?? false)
            && $transaction->household_id === $this->household()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $householdId = $this->household()->id;

        return [
            'assignee_id' => ['nullable', 'integer', Rule::exists('household_members', 'user_id')->where('household_id', $householdId)],
        ];
    }
}
