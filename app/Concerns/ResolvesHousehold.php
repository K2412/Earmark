<?php

namespace App\Concerns;

use App\Models\Household;
use Illuminate\Http\Request;

trait ResolvesHousehold
{
    protected function household(?Request $request = null): Household
    {
        $user = $request?->user() ?? request()->user();
        $household = $user?->household();

        abort_unless($household, 403, 'You must belong to a household.');

        $this->authorize('view', $household);

        return $household;
    }
}
