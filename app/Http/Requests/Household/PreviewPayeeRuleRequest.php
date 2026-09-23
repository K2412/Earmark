<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewPayeeRuleRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $householdId = $this->household()->id;

        return [
            'pattern' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'string', Rule::exists('categories', 'id')->where('household_id', $householdId)],
            'bucket_id' => ['nullable', 'string', Rule::exists('buckets', 'id')->where('household_id', $householdId)],
            'rename_to' => ['nullable', 'string', 'max:255'],
            'hide_from_reports' => ['nullable', 'boolean'],
            'mark_for_review' => ['nullable', 'boolean'],
        ];
    }
}
