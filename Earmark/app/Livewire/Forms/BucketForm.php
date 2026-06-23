<?php

namespace App\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class BucketForm extends Form
{
    #[Validate('required|string|max:255|unique:buckets,name')]
    public string $name = '';

    #[Validate]
    public string $kind = 'ongoing';

    #[Validate]
    public int $monthly_obligation = 0;

    #[Validate]
    public ?int $target_amount = null;

    #[Validate]
    public string $target_date = '';

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['goal', 'ongoing'])],
            'monthly_obligation' => ['required', 'integer', 'min:0'],
            'target_amount' => ['nullable', 'integer', 'min:0'],
            'target_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function defaults(): void
    {
        $this->name = '';
        $this->kind = 'ongoing';
        $this->monthly_obligation = 0;
        $this->target_amount = null;
        $this->target_date = now()->addYear()->toDateString();
    }
}
