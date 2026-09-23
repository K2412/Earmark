<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use App\Models\Holding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHoldingRequest extends FormRequest
{
    use AuthorizesHousehold;

    public function authorize(): bool
    {
        $holding = $this->route('holding');

        return $holding instanceof Holding
            && ($this->user()?->can('view', $this->household()) ?? false)
            && $holding->household_id === $this->household()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $householdId = $this->household()->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['nullable', 'string', 'max:32'],
            'asset_class' => ['required', Rule::in(Holding::ASSET_CLASSES)],
            'cost_basis_cents' => ['required', 'integer', 'min:0'],
            'market_value_cents' => ['required', 'integer', 'min:0'],
            'target_allocation_bps' => ['nullable', 'integer', 'between:0,10000'],
            'account_id' => ['nullable', 'string', Rule::exists('accounts', 'id')->where('household_id', $householdId)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
