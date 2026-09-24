<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Enums\Permission as P;
use App\Enums\Role as RoleEnum;
use App\Models\Tenant\Setting;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Brings a newly provisioned shop up to a usable state.
 *
 * Runs inside the tenant's own database, so every row it writes belongs to
 * that one shop. Idempotent: safe to re-run against an existing shop when
 * new permissions are added.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (P::all() as $permission) {
            Permission::findOrCreate($permission, 'tenant');
        }

        foreach (RoleEnum::cases() as $case) {
            $role = Role::findOrCreate($case->value, 'tenant');
            // syncPermissions rather than give: re-running must remove
            // permissions a role no longer has, not just add new ones.
            $role->syncPermissions($case->permissions());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seedSettings();
    }

    /**
     * Defaults for the values that used to be hard-coded in the invoice
     * template. The shop owner edits these in Settings.
     */
    private function seedSettings(): void
    {
        $defaults = [
            'shop.name' => ['value' => tenant('name') ?? 'My Shop', 'group' => 'shop'],
            'shop.address' => ['value' => '', 'group' => 'shop'],
            'shop.phone' => ['value' => '', 'group' => 'shop'],
            'shop.email' => ['value' => '', 'group' => 'shop'],

            'locale.currency' => ['value' => 'BDT', 'group' => 'locale'],
            'locale.currency_symbol' => ['value' => '৳', 'group' => 'locale'],
            'locale.timezone' => ['value' => 'Asia/Dhaka', 'group' => 'locale'],

            // Tax is off until a shop opts in, so invoices do not silently
            // gain a line the shop never charged.
            'tax.enabled' => ['value' => false, 'group' => 'tax'],
            'tax.inclusive' => ['value' => false, 'group' => 'tax'],

            'invoice.prefix' => ['value' => 'INV', 'group' => 'invoice'],
            'invoice.footer' => ['value' => '', 'group' => 'invoice'],
            'invoice.terms' => ['value' => '', 'group' => 'invoice'],

            // Per-product warranty overrides this.
            'warranty.default_days' => ['value' => 0, 'group' => 'warranty'],
        ];

        foreach ($defaults as $key => $config) {
            // firstOrCreate, not updateOrCreate: never overwrite a value the
            // shop has already customised.
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $config['value'], 'group' => $config['group']],
            );
        }
    }
}
