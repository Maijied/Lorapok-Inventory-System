<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Enums\Permission as P;
use App\Enums\Role as RoleEnum;
use App\Models\Tenant\CashRegister;
use App\Models\Tenant\Category;
use App\Models\Tenant\Location;
use App\Models\Tenant\PaymentMethod;
use App\Models\Tenant\Setting;
use App\Models\Tenant\TaxRate;
use App\Models\Tenant\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
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
        $this->seedCatalogDefaults();
    }

    /**
     * Baseline catalogue rows so a new shop can add a product immediately.
     *
     * The old system had a categories table with no UI and no seed data at
     * all, so the dashboard rendered blank cards for thirteen hard-coded
     * category names that existed only in a Blade template.
     */
    private function seedCatalogDefaults(): void
    {
        foreach ([
            ['name' => 'Piece', 'code' => 'pc', 'precision' => 0],
            ['name' => 'Pack', 'code' => 'pack', 'precision' => 0],
            ['name' => 'Metre', 'code' => 'm', 'precision' => 2],
        ] as $unit) {
            Unit::firstOrCreate(['code' => $unit['code']], $unit);
        }

        // Zero-rated by default; a shop opts into real rates in Settings.
        TaxRate::firstOrCreate(
            ['name' => 'No tax'],
            ['rate_bps' => 0, 'is_inclusive' => false, 'is_active' => true],
        );

        // Bangladesh retail reality: cash at the counter, mobile money, then
        // cards and bank transfer. "On account" records that nothing changed
        // hands and the balance is carried.
        foreach ([
            ['name' => 'Cash', 'type' => 'cash', 'sort_order' => 1],
            ['name' => 'bKash', 'type' => 'mobile', 'sort_order' => 2],
            ['name' => 'Nagad', 'type' => 'mobile', 'sort_order' => 3],
            ['name' => 'Card', 'type' => 'card', 'sort_order' => 4],
            ['name' => 'Bank transfer', 'type' => 'bank', 'sort_order' => 5],
            ['name' => 'On account', 'type' => 'credit', 'sort_order' => 6],
        ] as $method) {
            PaymentMethod::firstOrCreate(['name' => $method['name']], $method + ['is_active' => true]);
        }

        // Every shop has at least one place stock can live. Multi-location
        // shops add warehouses later; the ledger is already keyed by location.
        $location = Location::firstOrCreate(
            ['code' => 'main'],
            ['name' => 'Main Shop', 'type' => 'shop', 'is_default' => true, 'is_active' => true],
        );

        // ...and one till, so a cashier can open a shift on day one.
        CashRegister::firstOrCreate(
            ['location_id' => $location->id, 'name' => 'Counter 1'],
            ['is_active' => true],
        );

        foreach ([
            'Mobile Phone', 'Headphones & Speakers', 'Chargers & Adapters',
            'Cables', 'Earbuds', 'Phone Cases', 'Memory Cards',
            'Power Banks', 'Smart Watches', 'Accessories',
        ] as $name) {
            Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );
        }
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
            // Bangladeshi retail does not settle in paisa.
            'invoice.round_to_whole' => ['value' => true, 'group' => 'invoice'],

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
