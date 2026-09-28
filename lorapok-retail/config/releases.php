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

    // The copy the release workflow commits back. Configurable only so a
    // test can point at a file that is not there: once a real release has
    // landed this file exists in the repo, and the empty-state branch
    // becomes unreachable from a test that relies on the repo's own copy.
    'manifest' => resource_path('releases.json'),
];
