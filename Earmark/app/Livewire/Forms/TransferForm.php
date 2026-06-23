<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class TransferForm extends Form
{
    #[Validate('required|date_format:Y-m-d')]
    public string $date = '';

    #[Validate('required|string|exists:accounts,id')]
    public string $from_account_id = '';

    #[Validate('required|string|exists:accounts,id|different:from_account_id')]
    public string $to_account_id = '';

    #[Validate('required|integer|min:1')]
    public int $amount = 0;

    #[Validate('nullable|string|max:255')]
    public ?string $memo = null;

    public function defaults(): void
    {
        $this->date = now()->toDateString();
        $this->from_account_id = '';
        $this->to_account_id = '';
        $this->amount = 0;
        $this->memo = null;
    }
}
