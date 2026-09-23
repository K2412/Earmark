<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use App\Models\Bucket;
use Illuminate\Foundation\Http\FormRequest;

class SetBucketObligationRequest extends FormRequest
{
    use AuthorizesHousehold;

    public function authorize(): bool
    {
        $bucket = $this->route('bucket');

        return $bucket instanceof Bucket
            && ($this->user()?->can('view', $this->household()) ?? false)
            && $bucket->household_id === $this->household()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'monthly_obligation' => ['required', 'integer', 'min:0'],
            'target_amount' => ['nullable', 'integer', 'min:0'],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
            'effective_year' => ['required', 'integer', 'between:2000,2100'],
            'effective_month' => ['required', 'integer', 'between:1,12'],
        ];
    }
}
