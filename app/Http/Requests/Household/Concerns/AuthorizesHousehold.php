<?php

namespace App\Http\Requests\Household\Concerns;

use App\Models\Household;

trait AuthorizesHousehold
{
    protected function household(): Household
    {
        $household = $this->user()?->household();

        abort_unless($household, 403, 'You must belong to a household.');

        return $household;
    }

    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->household()) ?? false;
    }
}
