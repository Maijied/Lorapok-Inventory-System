<?php

declare(strict_types=1);

namespace App\Domain\Central;

use App\Enums\SaleStatus;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cross-shop analytics for a database-per-tenant system.
 *
 * Shops live in separate databases, so a super-admin dashboard cannot JOIN
 * across them. The two obvious alternatives are both wrong:
 *
 *   - cross-database JOINs break the moment shops move to separate hosts;
 *   - querying every tenant on page load costs one connection per shop and
 *     degrades linearly forever.
 *
 * Instead each shop is aggregated inside its own database and one row per
 * shop per day is written centrally. Dashboards then read a single table,
 * so their cost is constant no matter how many shops exist.
 *
 * The trade-off is stated rather than hidden: figures are as fresh as the
 * last run, and the dashboard says so.
 */
class MetricsRollup
{
    /**
     * Roll up one shop for one day.
     *
     * Idempotent: re-running any day overwrites that day's row, so a repair
     * is simply a re-run.
     */
    public function forTenant(Tenant $tenant, CarbonImmutable $day): void
    {
        $start = $day->startOfDay();
        $end = $day->endOfDay();

        tenancy()->initialize($tenant);

        try {
            $metrics = $this->gather($start, $end);
        } finally {
            // Always return to central, even if a shop's database is broken,
            // or the next tenant would be rolled up against the wrong one.
            tenancy()->end();
        }

        DB::connection(config('tenancy.database.central_connection'))
            ->table('tenant_daily_metrics')
            ->updateOrInsert(
                ['tenant_id' => $tenant->id, 'date' => $day->toDateString()],
                $metrics + ['computed_at' => now(), 'updated_at' => now(), 'created_at' => now()],
            );
    }

    /**
     * Roll up every active shop.
     *
     * Yesterday is recomputed alongside today because a sale can be voided
     * or returned after midnight, which changes a day already rolled up.
     *
     * @return int number of shop-days written
     */
    public function run(?CarbonImmutable $day = null, bool $includeYesterday = true): int
    {
        $day ??= CarbonImmutable::now();
        $written = 0;

        foreach (Tenant::query()->cursor() as $tenant) {
            $this->forTenant($tenant, $day);
            $written++;

            if ($includeYesterday) {
                $this->forTenant($tenant, $day->subDay());
                $written++;
            }
        }

        return $written;
    }

    /**
     * @return array<string, mixed>
     */
    private function gather(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $sales = DB::table('sales')
            ->where('status', SaleStatus::Completed->value)
            ->whereBetween('sold_at', [$start, $end])
            ->selectRaw('
                COUNT(*) as sales_count,
                COALESCE(SUM(subtotal_minor), 0) as gross,
                COALESCE(SUM(discount_minor), 0) as discount,
                COALESCE(SUM(tax_minor), 0) as tax,
                COALESCE(SUM(cogs_minor), 0) as cogs,
                COALESCE(SUM(paid_minor), 0) as paid,
                COALESCE(SUM(due_minor), 0) as due,
                MAX(currency) as currency
            ')
            ->first();

        $returns = DB::table('sale_returns')
            ->whereBetween('returned_at', [$start, $end])
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(refund_total_minor), 0) as amount')
            ->first();

        $purchases = DB::table('purchase_orders')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(total_minor), 0) as amount')
            ->first();

        $stock = DB::table('stock_levels')
            ->selectRaw('COALESCE(SUM(quantity), 0) as units, COALESCE(SUM(value_minor), 0) as value')
            ->first();

        $gross = (int) ($sales->gross ?? 0);
        $discount = (int) ($sales->discount ?? 0);
        $returned = (int) ($returns->amount ?? 0);
        $cogs = (int) ($sales->cogs ?? 0);
        $net = $gross - $discount - $returned;

        return [
            // Held per row and never summed across currencies without an
            // explicit FX table; silent cross-currency addition is the
            // classic way a multi-shop total becomes quietly wrong.
            'currency' => $sales->currency ?? 'BDT',

            'sales_count' => (int) ($sales->sales_count ?? 0),
            'sales_gross_minor' => $gross,
            'sales_discount_minor' => $discount,
            'sales_tax_minor' => (int) ($sales->tax ?? 0),
            'sales_net_minor' => $net,
            'cogs_minor' => $cogs,
            'gross_margin_minor' => $net - $cogs,

            'returns_count' => (int) ($returns->c ?? 0),
            'returns_minor' => $returned,

            'payments_received_minor' => (int) ($sales->paid ?? 0),
            'outstanding_due_minor' => (int) ($sales->due ?? 0),

            'purchases_count' => (int) ($purchases->c ?? 0),
            'purchases_minor' => (int) ($purchases->amount ?? 0),

            'active_users' => (int) DB::table('users')->whereNull('deleted_at')->where('is_active', true)->count(),
            'products_active' => (int) DB::table('products')->whereNull('deleted_at')->where('is_active', true)->count(),
            'stock_units' => (float) ($stock->units ?? 0),
            'stock_value_minor' => (int) ($stock->value ?? 0),
        ];
    }
}
