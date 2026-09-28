<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Digital Asset Links, for the Android app.
 *
 * Chrome fetches this before it will run the origin as an installed app
 * rather than in a browser tab with a URL bar. It has to answer without a
 * session, because Chrome asks before anyone signs in.
 *
 * 404 rather than an empty statement when no fingerprint is configured:
 * Chrome caches a failed verification, and an empty-but-valid file would
 * teach it that this origin is definitively not associated with any app.
 * A 404 is retried.
 */
final class AssetLinksController
{
    public function __invoke(): JsonResponse
    {
        $fingerprints = config('android.cert_fingerprints');

        abort_if($fingerprints === [], 404);

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => config('android.package'),
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]]);
    }
}
