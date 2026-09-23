<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsAllowlisted
{
    /**
     * Redirect any auth attempt from a non-allowlisted email to home.
     *
     * Applies to every authentication route that carries an email (sign in,
     * sign up, password reset). The allowlist is configured via the
     * AUTH_ALLOWLIST environment variable; see config/access.php.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->input('email');

            if (is_string($email) && $email !== '' && ! $this->isAllowlisted($email)) {
                return redirect()->route('home');
            }
        }

        return $next($request);
    }

    private function isAllowlisted(string $email): bool
    {
        return in_array(mb_strtolower(trim($email)), config('access.auth_allowlist', []), true);
    }
}
