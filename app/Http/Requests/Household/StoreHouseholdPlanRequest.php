<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;

class StoreHouseholdPlanRequest extends FormRequest
{
    use AuthorizesHousehold;

    protected function prepareForValidation(): void
    {
        if ($this->input('windfall_year') === '') {
            $this->merge(['windfall_year' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_cents' => ['required', 'integer', 'min:0'],
            'target_year' => ['required', 'integer', 'min:'.now()->year, 'max:2100'],
            'inflation_bps' => ['required', 'integer', 'min:0', 'max:2000'],
            'return_bps' => ['required', 'integer', 'min:0', 'max:3000'],
            'windfall_cents' => ['nullable', 'integer', 'min:0'],
            'windfall_year' => ['nullable', 'integer', 'min:'.now()->year, 'max:2100'],
            'phases' => ['required', 'array', 'min:1'],
            'phases.*.start_year' => ['required', 'integer', 'min:'.now()->year, 'max:2100'],
            'phases.*.end_year' => ['required', 'integer', 'gte:phases.*.start_year', 'max:2100'],
            'phases.*.annual_contribution_cents' => ['required', 'integer', 'min:0'],
        ];
    }
}
