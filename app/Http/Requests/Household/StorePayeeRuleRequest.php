<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayeeRuleRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $householdId = $this->household()->id;

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'pattern' => ['required', 'string', 'max:255'],
            'enabled' => ['required', 'boolean'],
            'category_id' => ['nullable', 'string', Rule::exists('categories', 'id')->where('household_id', $householdId)],
            'bucket_id' => ['nullable', 'string', Rule::exists('buckets', 'id')->where('household_id', $householdId)],
            'rename_to' => ['nullable', 'string', 'max:255'],
            'hide_from_reports' => ['required', 'boolean'],
            'mark_for_review' => ['required', 'boolean'],
            'auto_apply' => ['required', 'boolean'],
        ];
    }
}
