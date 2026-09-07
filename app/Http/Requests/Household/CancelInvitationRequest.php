<?php

namespace App\Http\Requests\Household;

use App\Http\Requests\Household\Concerns\AuthorizesHousehold;
use Illuminate\Foundation\Http\FormRequest;

class CancelInvitationRequest extends FormRequest
{
    use AuthorizesHousehold;

    public function authorize(): bool
    {
        return $this->user()?->can('cancelInvitation', $this->household()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
