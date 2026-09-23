---
paths:
  - app/Http/Middleware/EnsureEmailIsAllowlisted.php
---

# Middleware

## Sign-up / sign-in restricted to an email allowlist
Only emails in the `AUTH_ALLOWLIST` env var (comma-separated → parsed in config/access.php as `access.auth_allowlist`) may authenticate. `EnsureEmailIsAllowlisted` redirects any POST carrying a non-allowlisted `email` to route('home'). It is wired into the whole Fortify auth surface via config/fortify.php 'middleware' (covers login, forgot-password, reset-password) and into registration via the register.store route in routes/web.php. An empty allowlist denies everyone (fail closed). Real emails are NEVER committed — they live only in `.env` / the deployment env. Consequence for tests: phpunit.xml sets a fake `AUTH_ALLOWLIST` (@example.test); use the `allowlistedEmail()` helper (tests/Pest.php) for any POST to login.store/register.store/password.email, or it will be bounced home. actingAs() bypasses this.
