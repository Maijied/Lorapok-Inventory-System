<?php

declare(strict_types=1);

return [
    // Read through config rather than env() at the call site: env() returns
    // null once config is cached in production, which is how tenancy's root
    // domain broke in an earlier phase.
    'repository' => env('RELEASES_REPOSITORY', 'Maijied/Lorapok-Inventory-System'),

    // Optional. Unauthenticated GitHub allows 60 requests an hour per IP; at
    // a one-hour cache that is one call an hour, so a token is only worth
    // setting if this app shares an egress address with other GitHub traffic.
    'token' => env('RELEASES_GITHUB_TOKEN'),
];
