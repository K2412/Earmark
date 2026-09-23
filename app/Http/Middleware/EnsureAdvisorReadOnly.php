<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A least-privilege advisor may read every household surface but never mutate one
 * — no moving money, inviting members, or changing settings (task #1261).
 */
class EnsureAdvisorReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $user = $request->user();
        $household = $user?->household();

        abort_if(
            $household !== null && ($user->householdRole($household)?->isReadOnly() ?? false),
            403,
            'Advisors have read-only access to this household.',
        );

        return $next($request);
    }
}
