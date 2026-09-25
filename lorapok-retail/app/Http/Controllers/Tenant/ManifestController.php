<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * The shop's own web app manifest.
 *
 * Served by Laravel rather than shipped as a static file, because it has to
 * carry this shop's name and colour: an installed app should appear on a
 * cashier's home screen as "Karim Mobile", not as "Lorapok Retail".
 */
class ManifestController
{
    public function __invoke(): JsonResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        $name = $tenant instanceof Tenant ? $tenant->name : (string) config('app.name');
        $accent = $this->safeAccent($tenant instanceof Tenant ? $tenant->accent : null);

        return response()->json([
            'name' => $name,
            'short_name' => mb_substr($name, 0, 12),
            'description' => "{$name} point of sale, powered by Lorapok Retail.",
            'lang' => 'en',
            'display' => 'standalone',
            'orientation' => 'any',
            // The till is what a cashier installs this for.
            'start_url' => '/pos',
            'scope' => '/',
            'background_color' => '#06080d',
            'theme_color' => '#06080d',
            'icons' => [
                [
                    'src' => route('tenant.icon', ['size' => 192]),
                    'sizes' => '192x192',
                    'type' => 'image/svg+xml',
                    // Maskable so Android does not letterbox it in a white box.
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => route('tenant.icon', ['size' => 512]),
                    'sizes' => '512x512',
                    'type' => 'image/svg+xml',
                    'purpose' => 'any maskable',
                ],
            ],
            'shortcuts' => [
                ['name' => 'New sale', 'url' => '/pos'],
                ['name' => 'Products', 'url' => '/products'],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    /**
     * A stored value is not a trusted value: the accent reaches an SVG, so it
     * is validated here as well as where it is written.
     */
    private function safeAccent(?string $accent): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', (string) $accent) ? $accent : '#7c5cff';
    }
}
