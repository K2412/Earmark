<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;

class StoreScenarioRequest extends FormRequest
{
    use AuthorizesHousehold;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'base_year' => ['required', 'integer', 'between:2000,2100'],
            'horizon_years' => ['required', 'integer', 'between:1,80'],
            'starting_investable_cents' => ['required', 'integer', 'min:0'],
            'annual_contribution_cents' => ['required', 'integer', 'min:0'],
            'contribution_years' => ['required', 'integer', 'between:0,80'],
            'return_bps' => ['required', 'integer', 'between:0,30000'],
            'inflation_bps' => ['required', 'integer', 'between:0,30000'],
            'windfall_cents' => ['nullable', 'integer', 'min:0'],
            'windfall_year' => ['nullable', 'integer', 'between:2000,2100'],
            'drawdown_start_year' => ['nullable', 'integer', 'between:2000,2100'],
            'drawdown_annual_cents' => ['nullable', 'integer', 'min:0'],
            'downsizing_year' => ['nullable', 'integer', 'between:2000,2100'],
            'downsizing_proceeds_cents' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
