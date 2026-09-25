<?php

declare(strict_types=1);

namespace App\Domain\Central;

use App\Models\Tenant;
use App\Models\Tenant\User as ShopUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creating and retiring shops.
 *
 * Provisioning a shop means creating a database, running its migrations,
 * seeding its roles and defaults, and giving its owner a way in. All of that
 * happens behind one call so a half-created shop cannot be left behind.
 */
class ShopProvisioner
{
    /**
     * Create a shop and its owner.
     *
     * @throws InvalidShop
     */
    public function create(
        string $name,
        string $slug,
        string $ownerName,
        string $ownerEmail,
        string $ownerPassword,
        ?string $accent = null,
        ?User $actor = null,
    ): Tenant {
        $slug = Str::of($slug)->lower()->trim()->value();

        $this->assertSlugAvailable($slug);
        $this->assertAccentAllowed($accent);

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'accent' => $accent ?? Tenant::ACCENT_PALETTE[0],
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ]);

        // Creating the domain is what makes the shop reachable. The tenant's
        // database is created and migrated by the TenantCreated pipeline.
        $tenant->domains()->create(['domain' => $this->hostFor($slug)]);

        // The owner lives in the shop's own database, not the central one.
        tenancy()->initialize($tenant);

        try {
            ShopUser::create([
                'name' => $ownerName,
                'email' => $ownerEmail,
                'password' => $ownerPassword,
                'is_active' => true,
            ])->syncRoles(['owner']);
        } finally {
            tenancy()->end();
        }

        AuditLog::record('shop.created', $tenant, $actor, after: [
            'name' => $name,
            'slug' => $slug,
        ]);

        return $tenant->refresh();
    }

    /**
     * Suspend a shop.
     *
     * Its data is left intact and its database untouched — suspension is a
     * billing state, not a deletion. Staff simply cannot sign in.
     */
    public function suspend(Tenant $tenant, ?string $reason = null, ?User $actor = null): Tenant
    {
        if ($tenant->isSuspended()) {
            return $tenant;
        }

        $tenant->forceFill([
            'status' => 'suspended',
            'suspended_at' => now(),
        ])->save();

        AuditLog::record('shop.suspended', $tenant, $actor, after: ['reason' => $reason]);

        return $tenant->refresh();
    }

    public function restore(Tenant $tenant, ?User $actor = null): Tenant
    {
        if (! $tenant->isSuspended()) {
            return $tenant;
        }

        $tenant->forceFill([
            'status' => $tenant->trial_ends_at?->isFuture() ? 'trialing' : 'active',
            'suspended_at' => null,
        ])->save();

        AuditLog::record('shop.restored', $tenant, $actor);

        return $tenant->refresh();
    }

    /**
     * Change a shop's branding.
     *
     * The accent is interpolated into CSS, so it is validated here as well as
     * at render time — a stored value is not a trusted value.
     */
    public function updateBranding(
        Tenant $tenant,
        ?string $name = null,
        ?string $accent = null,
        ?string $theme = null,
        ?User $actor = null,
    ): Tenant {
        $this->assertAccentAllowed($accent);

        $before = ['name' => $tenant->name, 'accent' => $tenant->accent, 'theme' => $tenant->theme];

        $tenant->forceFill(array_filter([
            'name' => $name,
            'accent' => $accent,
            'theme' => in_array($theme, ['dark', 'light'], true) ? $theme : null,
        ]))->save();

        AuditLog::record('shop.branding_updated', $tenant, $actor, before: $before, after: [
            'name' => $tenant->name, 'accent' => $tenant->accent, 'theme' => $tenant->theme,
        ]);

        return $tenant->refresh();
    }

    /**
     * Permanently delete a shop and its database.
     *
     * Irreversible, so it refuses unless the caller confirms with the shop's
     * own slug — the same guard a careful operator would want.
     */
    public function destroy(Tenant $tenant, string $confirmSlug, ?User $actor = null): void
    {
        if ($confirmSlug !== $tenant->slug) {
            throw new InvalidShop('Confirm by typing the shop slug exactly.');
        }

        $slug = $tenant->slug;

        DB::transaction(function () use ($tenant) {
            $tenant->domains()->delete();
            // Deleting the tenant drops its database via stancl's pipeline.
            $tenant->delete();
        });

        AuditLog::record('shop.deleted', null, $actor, before: ['slug' => $slug]);
    }

    private function assertSlugAvailable(string $slug): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,30}[a-z0-9]$/', $slug)) {
            throw new InvalidShop(
                'A shop address must be 3-32 characters of lowercase letters, numbers or hyphens.',
            );
        }

        if (in_array($slug, Tenant::RESERVED_SLUGS, true)) {
            throw new InvalidShop("\"{$slug}\" is reserved and cannot be used as a shop address.");
        }

        if (Tenant::where('slug', $slug)->exists()) {
            throw new InvalidShop("\"{$slug}\" is already taken.");
        }
    }

    /**
     * Only the approved palette is accepted.
     *
     * A free colour picker would let a shop choose something unreadable
     * against the surface colours, and the value ends up inside a style
     * attribute.
     */
    private function assertAccentAllowed(?string $accent): void
    {
        if ($accent === null) {
            return;
        }

        if (! in_array(strtolower($accent), array_map('strtolower', Tenant::ACCENT_PALETTE), true)) {
            throw new InvalidShop('That colour is not in the approved palette.');
        }
    }

    public function hostFor(string $slug): string
    {
        return $slug.'.'.config('tenancy.root_domain');
    }
}
