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
            'icons' => $this->icons($tenant),
            'shortcuts' => [
                ['name' => 'New sale', 'url' => '/pos'],
                ['name' => 'Products', 'url' => '/products'],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    }

    /**
     * The icons an installer should use.
     *
     * A shop that has uploaded a logo gets that; everyone else gets the
     * generated initial. One entry rather than two when there is a logo: it
     * is a single 512px square, and listing the same bytes twice under two
     * sizes would only make the cache work harder for no gain.
     *
     * @return array<int, array<string, string>>
     */
    private function icons(?Tenant $tenant): array
    {
        $logo = $tenant instanceof Tenant ? (string) $tenant->logo_path : '';

        if ($logo !== '') {
            return [[
                'src' => route('tenant.logo'),
                'sizes' => '512x512',
                'type' => 'image/png',
                // Honest: LogoService keeps the mark inside the safe area.
                'purpose' => 'any maskable',
            ]];
        }

        return array_map(fn (int $size): array => [
            'src' => route('tenant.icon', ['size' => $size]),
            'sizes' => "{$size}x{$size}",
            'type' => 'image/svg+xml',
            // Maskable so Android does not letterbox it in a white box.
            'purpose' => 'any maskable',
        ], [192, 512]);
    }

    /**
     * A stored value is not a trusted value: the accent reaches an SVG, so it
     * is validated here as well as where it is written.
     */
    private function safeAccent(?string $accent): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', (string) $accent) ? $accent : Tenant::ACCENT_PALETTE[0];
    }
}
