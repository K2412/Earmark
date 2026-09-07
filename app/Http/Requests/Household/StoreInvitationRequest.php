<?php

namespace App\Http\Requests\Household;

use App\Enums\HouseholdRole;
use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    use AuthorizesHousehold;

    public function authorize(): bool
    {
        return $this->user()?->can('inviteMember', $this->household()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(array_column(HouseholdRole::assignable(), 'value'))],
        ];
    }
}
