<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Email Allowlist
    |--------------------------------------------------------------------------
    |
    | Only these email addresses may sign up or sign in; every other address is
    | redirected home. Provide a comma-separated list via the AUTH_ALLOWLIST
    | environment variable so real addresses stay out of version control and
    | can be set per deployment. An empty list denies everyone (fail closed).
    |
    */

    'auth_allowlist' => array_values(array_filter(array_map(
        fn (string $email): string => mb_strtolower(trim($email)),
        explode(',', (string) env('AUTH_ALLOWLIST', '')),
    ))),

];
