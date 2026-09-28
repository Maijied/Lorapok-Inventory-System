<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use Illuminate\Http\JsonResponse;

/**
 * The manifest for the apex, as distinct from a shop's own.
 *
 * A shop's manifest carries that shop's name and colour, so an installed app
 * appears as "Karim Mobile". This one is the product itself, and it exists
 * for one reason: Bubblewrap reads a manifest to build the Android app, and
 * a Trusted Web Activity is bound to exactly one origin.
 *
 * That origin has to be the apex. Binding to a shop's subdomain would mean an
 * APK per shop, each needing its own Play listing and review, which is not
 * shippable. So the app opens here and the shop signs in with its own
 * address.
 */
final class CentralManifestController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name' => config('app.name'),
            'short_name' => 'Lorapok',
            'description' => 'Inventory and point of sale for shops that sell serialised goods.',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#06080d',
            'theme_color' => '#06080d',
            'icons' => [
                [
                    'src' => asset('android-chrome-192x192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => asset('android-chrome-512x512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }
}
