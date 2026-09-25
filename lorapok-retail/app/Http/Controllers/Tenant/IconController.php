<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Models\Tenant;
use Illuminate\Http\Response;

/**
 * A generated app icon in the shop's own accent colour.
 *
 * SVG rather than a stored PNG so a new shop has a usable icon the moment it
 * is created, with no upload step and no image processing. A shop that
 * uploads its own logo can override this later.
 */
class IconController
{
    public function __invoke(int $size = 512): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        $stored = $tenant instanceof Tenant ? $tenant->accent : '';
        $accent = preg_match('/^#[0-9a-f]{6}$/i', $stored) ? $stored : '#7c5cff';

        $shopName = $tenant instanceof Tenant ? $tenant->name : 'L';
        $initial = mb_strtoupper(mb_substr(trim($shopName), 0, 1));
        $initial = htmlspecialchars($initial, ENT_QUOTES | ENT_XML1, 'UTF-8');

        // 20% safe-area padding so a maskable icon is not cropped into.
        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="{$size}" height="{$size}">
            <rect width="512" height="512" fill="#06080d"/>
            <circle cx="256" cy="256" r="150" fill="{$accent}"/>
            <text x="256" y="256" fill="#ffffff" font-family="DM Sans, system-ui, sans-serif"
                  font-size="170" font-weight="600" text-anchor="middle"
                  dominant-baseline="central">{$initial}</text>
        </svg>
        SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
