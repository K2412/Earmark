<?php

namespace App\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class AccountForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate]
    public string $type = 'chequing';

    #[Validate]
    public int $starting_balance = 0;

    #[Validate]
    public string $starting_balance_date = '';

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['chequing', 'savings', 'credit_card', 'cash', 'investment', 'other'])],
            'starting_balance' => ['required', 'integer'],
            'starting_balance_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function defaults(): void
    {
        $this->name = '';
        $this->type = 'chequing';
        $this->starting_balance = 0;
        $this->starting_balance_date = now()->toDateString();
    }
}
