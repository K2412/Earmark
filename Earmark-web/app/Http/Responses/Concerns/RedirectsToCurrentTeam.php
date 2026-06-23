<?php

namespace App\Http\Responses\Concerns;

trait RedirectsToCurrentTeam
{
    protected function redirectPathForCurrentTeam($request, string $redirect): string
    {
        return route('household.dashboard', absolute: false);
    }
}
