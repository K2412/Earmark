<?php

namespace App\Livewire\Forms;

use App\Enums\HouseholdRole;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class InvitationForm extends Form
{
    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate]
    public string $role = 'member';

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'role' => ['required', Rule::in(array_map(fn (HouseholdRole $r) => $r->value, HouseholdRole::cases()))],
        ];
    }

    public function defaults(): void
    {
        $this->email = '';
        $this->role = 'member';
    }
}
