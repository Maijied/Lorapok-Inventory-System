<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Carbon;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A shop.
 *
 * Each tenant owns a separate MySQL database (`tenant_<id>`), so one shop can
 * never read another's rows even if a query forgets to scope itself.
 *
 * Cast types, which PHPStan cannot infer from stancl's `data` json column.
 *
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property string $accent
 * @property string $theme
 * @property ?Carbon $trial_ends_at
 * @property ?Carbon $suspended_at
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    /**
     * Columns promoted out of the `data` json blob into real columns.
     *
     * stancl stores anything not listed here inside `data`. These are listed
     * because the super admin queries, sorts and filters on them.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'logo_path',
            'accent',
            'theme',
            'status',
            'trial_ends_at',
            'suspended_at',
        ];
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Subdomains that must never be handed to a shop, because they collide with
     * central routing or with infrastructure hostnames.
     */
    public const RESERVED_SLUGS = [
        // Central routing and infrastructure.
        'admin', 'api', 'app', 'assets', 'billing', 'blog', 'cdn', 'dashboard',
        'developer', 'docs', 'download', 'downloads', 'files', 'ftp', 'help',
        'imap', 'mail', 'media', 'mx', 'ns1', 'ns2', 'pop', 'pricing', 'retail',
        'smtp', 'static', 'status', 'support', 'test', 'staging', 'webmail',
        'www',

        // Shops live at <slug>.lorapok.tech, sharing the apex with every other
        // Lorapok Labs product. Handing one of these to a shop would take a
        // live site off the air, so they are reserved here rather than
        // discovered in production.
        'ai', 'atlas', 'curse', 'cursor', 'cursor-dev', 'labs', 'loragent',
        'maizied', 'mission-control', 'reportkit', 'seeyou',
    ];

    /**
     * The approved accent palette. Tenants pick from this rather than supplying
     * arbitrary CSS — every value here is contrast-checked against the
     * Mission Control surface colours.
     */
    public const ACCENT_PALETTE = [
        // Every entry must stay legible in BOTH roles: as a button fill with
        // Contrast::onColor() text on it, and as text on the dark surface.
        // ContrastTest enforces both — the original '#7c5cff' failed the first
        // at 4.35:1 with white and 4.11:1 with ink, so the primary action on
        // every screen had a label below the AA floor either way.
        '#9075ff', // violet — Lorapok primary
        '#4d9fff', // blue
        '#34d399', // green
        '#fbbf24', // amber
        '#ff6b6b', // red
        '#f472b6', // pink
        '#22d3ee', // cyan
        '#a78bfa', // lavender
    ];

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }
}
