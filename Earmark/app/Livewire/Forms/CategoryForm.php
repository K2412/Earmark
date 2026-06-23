<?php

namespace App\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class CategoryForm extends Form
{
    #[Validate('required|string|max:255|unique:categories,name')]
    public string $name = '';

    #[Validate]
    public string $type = 'other';

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['income', 'housing', 'transportation', 'food', 'household', 'personal', 'health', 'debt', 'savings', 'fees', 'other'])],
        ];
    }

    public function defaults(): void
    {
        $this->name = '';
        $this->type = 'other';
    }
}
