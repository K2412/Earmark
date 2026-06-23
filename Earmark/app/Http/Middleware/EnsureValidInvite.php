<?php

namespace App\Http\Middleware;

use App\Models\HouseholdInvitation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidInvite
{
    /**
     * Registration is closed unless the request carries (or has already redeemed) a valid invite.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->query('invite') ?? $request->session()->get('pending_invite');

        if (! $code) {
            abort(403, 'Registration is by invitation only.');
        }

        $invitation = HouseholdInvitation::query()
            ->where('code', $code)
            ->whereNull('accepted_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->first();

        if (! $invitation) {
            abort(403, 'This invitation is invalid or has expired.');
        }

        $request->session()->put('pending_invite', $invitation->code);

        return $next($request);
    }
}
