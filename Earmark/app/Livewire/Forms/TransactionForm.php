<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class TransactionForm extends Form
{
    #[Validate('required|date_format:Y-m-d')]
    public string $date = '';

    #[Validate('required|string|exists:accounts,id')]
    public string $account_id = '';

    #[Validate('required|string|max:255')]
    public string $payee = '';

    #[Validate('nullable|string|exists:categories,id')]
    public ?string $category_id = null;

    #[Validate('nullable|string|exists:buckets,id')]
    public ?string $bucket_id = null;

    #[Validate('required|integer')]
    public int $amount = 0;

    #[Validate('nullable|string|max:255')]
    public ?string $memo = null;

    public function defaults(): void
    {
        $this->date = now()->toDateString();
        $this->account_id = '';
        $this->payee = '';
        $this->category_id = null;
        $this->bucket_id = null;
        $this->amount = 0;
        $this->memo = null;
    }
}
