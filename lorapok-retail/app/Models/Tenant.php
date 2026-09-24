<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A shop.
 *
 * Each tenant owns a separate MySQL database (`tenant_<id>`), so one shop can
 * never read another's rows even if a query forgets to scope itself.
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
        'admin', 'api', 'app', 'assets', 'billing', 'blog', 'cdn', 'dashboard',
        'developer', 'docs', 'files', 'ftp', 'help', 'imap', 'mail', 'media',
        'ns1', 'ns2', 'pop', 'retail', 'smtp', 'static', 'status', 'support',
        'test', 'staging', 'webmail', 'www',
    ];

    /**
     * The approved accent palette. Tenants pick from this rather than supplying
     * arbitrary CSS — every value here is contrast-checked against the
     * Mission Control surface colours.
     */
    public const ACCENT_PALETTE = [
        '#7c5cff', // violet — Lorapok primary
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
