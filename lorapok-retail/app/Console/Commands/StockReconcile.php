<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StockItemStatus;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Prove that the cached stock levels still match the ledger.
 *
 * `stock_movements` is authoritative; `stock_levels` is a cache kept in step
 * inside the same transaction. This command recomputes the cache from the
 * ledger and reports any drift, so a discrepancy is detected by a scheduled
 * run rather than discovered by a customer at the counter.
 *
 * Evidence over assertion: without this, "the cache is always correct" is
 * only a claim.
 */
class StockReconcile extends Command
{
    protected $signature = 'stock:reconcile
                            {--tenant= : Reconcile a single shop by slug}
                            {--fix : Repair drift instead of only reporting it}';

    protected $description = 'Check cached stock levels against the immutable stock ledger';

    public function handle(): int
    {
        /** @var Collection<int, Tenant> $tenants */
        $tenants = $this->option('tenant')
            ? Tenant::query()->where('slug', $this->option('tenant'))->get()
            : Tenant::query()->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching shops.');

            return self::FAILURE;
        }

        $totalDrift = 0;

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            try {
                $totalDrift += $this->reconcileTenant($tenant->slug);
            } finally {
                tenancy()->end();
            }
        }

        if ($totalDrift === 0) {
            $this->info('No drift. Every cached level matches the ledger.');

            return self::SUCCESS;
        }

        if ($this->option('fix')) {
            $this->warn("Repaired {$totalDrift} level(s).");

            return self::SUCCESS;
        }

        // Non-zero exit so a scheduler or CI job treats drift as a failure.
        $this->error("Found {$totalDrift} level(s) that disagree with the ledger. Re-run with --fix to repair.");

        return self::FAILURE;
    }

    private function reconcileTenant(string $slug): int
    {
        // The ledger's own view of every variant that has ever moved.
        $ledger = DB::table('stock_movements')
            ->select('location_id', 'product_variant_id', DB::raw('SUM(quantity) as qty'))
            ->groupBy('location_id', 'product_variant_id')
            ->get()
            ->keyBy(fn ($row) => "{$row->location_id}:{$row->product_variant_id}");

        $cached = DB::table('stock_levels')
            ->get()
            ->keyBy(fn ($row) => "{$row->location_id}:{$row->product_variant_id}");

        $drift = 0;

        foreach ($ledger as $key => $row) {
            $expected = round((float) $row->qty, 4);
            $actual = round((float) ($cached[$key]->quantity ?? 0), 4);

            if ($expected === $actual) {
                continue;
            }

            $drift++;
            $this->line("  [{$slug}] {$key}: cached {$actual}, ledger {$expected}");

            if ($this->option('fix')) {
                DB::table('stock_levels')
                    ->updateOrInsert(
                        ['location_id' => $row->location_id, 'product_variant_id' => $row->product_variant_id],
                        ['quantity' => $expected, 'updated_at' => now()],
                    );
            }
        }

        // A cached level with no ledger entries at all is also drift.
        foreach ($cached as $key => $row) {
            if ($ledger->has($key) || round((float) $row->quantity, 4) === 0.0) {
                continue;
            }

            $drift++;
            $this->line("  [{$slug}] {$key}: cached {$row->quantity}, ledger has no movements");

            if ($this->option('fix')) {
                DB::table('stock_levels')
                    ->where('location_id', $row->location_id)
                    ->where('product_variant_id', $row->product_variant_id)
                    ->update(['quantity' => 0, 'value_minor' => 0, 'updated_at' => now()]);
            }
        }

        $drift += $this->reconcileSerialisedCounts($slug);

        return $drift;
    }

    /**
     * For serialised products the quantity must equal the number of units
     * actually sitting in stock, or an IMEI has been lost or double-counted.
     */
    private function reconcileSerialisedCounts(string $slug): int
    {
        $drift = 0;

        $counts = DB::table('stock_items')
            ->where('status', StockItemStatus::InStock->value)
            ->select('location_id', 'product_variant_id', DB::raw('COUNT(*) as units'))
            ->groupBy('location_id', 'product_variant_id')
            ->get();

        foreach ($counts as $row) {
            $cached = (float) (DB::table('stock_levels')
                ->where('location_id', $row->location_id)
                ->where('product_variant_id', $row->product_variant_id)
                ->value('quantity') ?? 0);

            if (round($cached, 4) === (float) $row->units) {
                continue;
            }

            $drift++;
            $this->line(
                "  [{$slug}] {$row->location_id}:{$row->product_variant_id}: ".
                "level {$cached} but {$row->units} serialised unit(s) in stock"
            );
        }

        return $drift;
    }
}
