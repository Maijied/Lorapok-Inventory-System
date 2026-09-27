<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * The plans the public pricing page renders from.
 *
 * Seeded rather than hardcoded in a Blade template so an operator can change a
 * price without a deploy — which is the whole reason the pricing page reads
 * the table.
 *
 * Prices are in poisha (BDT minor units), like every other money column in
 * this system. 150000 is ৳1,500.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Counter',
                'slug' => 'counter',
                'description' => 'One shop, one till. Everything needed to sell and to know what you made.',
                'price_minor' => 150000,
                'sort_order' => 1,
                'limits' => ['products' => 500, 'staff_accounts' => 3, 'sales_per_month' => null],
            ],
            [
                'name' => 'Shop',
                'slug' => 'shop',
                'description' => 'For a busy shop with staff on shifts and stock coming in weekly.',
                'price_minor' => 350000,
                'sort_order' => 2,
                'limits' => ['products' => 5000, 'staff_accounts' => 10, 'sales_per_month' => null],
            ],
            [
                'name' => 'Chain',
                'slug' => 'chain',
                'description' => 'Several shops, seen together. Margin and stock across all of them.',
                'price_minor' => 900000,
                'sort_order' => 3,
                'limits' => ['products' => null, 'staff_accounts' => null, 'sales_per_month' => null],
            ],
        ];

        foreach ($plans as $spec) {
            $limits = $spec['limits'];
            unset($spec['limits']);

            // updateOrCreate so re-seeding a live database corrects the plans
            // rather than duplicating them — the slug is the stable identity.
            $plan = Plan::updateOrCreate(['slug' => $spec['slug']], $spec);

            foreach ($limits as $key => $value) {
                // null is unlimited, deliberately — a sentinel like -1
                // eventually gets compared with `<` somewhere and silently
                // caps the plan.
                $plan->limits()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }
    }
}
