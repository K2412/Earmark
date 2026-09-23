<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use App\Services\Canadian\CanadianAssumptionCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverrideAssumptionRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', Rule::in(CanadianAssumptionCatalogue::KEYS)],
            'value' => ['required', 'integer'],
            'unit' => ['required', Rule::in(['cents', 'bps', 'count'])],
            'effective_year' => ['required', 'integer', 'between:1990,2100'],
            'source_url' => ['nullable', 'url', 'max:255'],
            'source_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
