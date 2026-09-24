<?php

declare(strict_types=1);

namespace App\Domain\Stock;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\StockLevel;
use App\Models\Tenant\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * The only way stock ever changes.
 *
 * Nothing else may insert a stock movement or touch a stock level. Keeping a
 * single entry point is what allows the stock check, the ledger write and the
 * cache update to happen inside one transaction under a row lock — which is
 * what the old system got wrong in three separate places:
 *
 *   - it checked stock AFTER inserting the sale line
 *   - its update path had no stock check at all, so quantities went negative
 *   - two concurrent sales of the last unit could both pass the check
 */
class StockService
{
    /**
     * Apply one movement atomically.
     *
     * @throws InsufficientStock when an outbound movement would overdraw
     */
    public function apply(MovementIntent $intent): StockMovement
    {
        return DB::transaction(function () use ($intent) {
            // SELECT ... FOR UPDATE. Two tills selling the last handset at the
            // same moment serialise here: the second waits, re-reads, and is
            // refused. Without the lock both would see stock of 1 and succeed.
            $level = $this->lockLevel($intent->locationId, $intent->variantId);

            $onHand = (float) $level->quantity;

            if (! $intent->isInbound()) {
                $this->assertSufficient($intent, $onHand);
            }

            $unitCost = $intent->isInbound()
                ? (int) $intent->unitCostMinor
                // Outbound movements freeze the CURRENT average cost onto the
                // row. That snapshot is what makes historical margin correct
                // and immune to later price changes.
                : (int) $level->avg_cost_minor;

            $newQuantity = $onHand + $intent->quantity;
            $newAvgCost = $intent->type->affectsAverageCost()
                ? $this->weightedAverage($onHand, (int) $level->avg_cost_minor, $intent->absoluteQuantity(), $unitCost)
                : (int) $level->avg_cost_minor;

            $movement = StockMovement::create([
                'location_id' => $intent->locationId,
                'product_variant_id' => $intent->variantId,
                'stock_item_id' => $intent->stockItemId,
                'quantity' => $intent->quantity,
                'unit_cost_minor' => $unitCost,
                'type' => $intent->type,
                'reference_type' => $intent->reference ? $intent->reference::class : null,
                'reference_id' => $intent->reference?->getKey(),
                'occurred_at' => $intent->occurredAt ?? now(),
                'created_by' => $intent->actorId,
                'note' => $intent->note,
            ]);

            $this->writeLevel($intent->locationId, $intent->variantId, $newQuantity, $newAvgCost);

            return $movement;
        });
    }

    /**
     * Apply several movements as one unit — a whole sale, or a goods receipt.
     *
     * If any line is short, nothing is written at all.
     *
     * @param  array<int, MovementIntent>  $intents
     * @return array<int, StockMovement>
     */
    public function applyMany(array $intents): array
    {
        return DB::transaction(function () use ($intents) {
            return array_map(fn (MovementIntent $intent) => $this->apply($intent), $intents);
        });
    }

    /** Current quantity on hand, read from the cache. */
    public function onHand(int $locationId, int $variantId): float
    {
        return (float) (StockLevel::query()
            ->where('location_id', $locationId)
            ->where('product_variant_id', $variantId)
            ->value('quantity') ?? 0);
    }

    /** Recompute a level straight from the ledger, which is the truth. */
    public function quantityFromLedger(int $locationId, int $variantId): float
    {
        return (float) StockMovement::query()
            ->where('location_id', $locationId)
            ->where('product_variant_id', $variantId)
            ->sum('quantity');
    }

    /**
     * Weighted moving average.
     *
     * Chosen over FIFO layers deliberately: roughly a third of the code, and
     * accurate enough for a phone shop. The alternative — storing no cost at
     * all, as the old system did — makes margin permanently uncomputable.
     */
    private function weightedAverage(float $oldQty, int $oldAvg, float $inQty, int $inCost): int
    {
        $totalQty = $oldQty + $inQty;

        if ($totalQty <= 0) {
            return $inCost;
        }

        // Stock can be negative only if a reconcile was skipped; fall back to
        // the incoming cost rather than producing a nonsensical average.
        if ($oldQty <= 0) {
            return $inCost;
        }

        return (int) round((($oldQty * $oldAvg) + ($inQty * $inCost)) / $totalQty);
    }

    private function assertSufficient(MovementIntent $intent, float $onHand): void
    {
        $requested = $intent->absoluteQuantity();

        if ($requested <= $onHand) {
            return;
        }

        $variant = ProductVariant::query()->with('product')->find($intent->variantId);
        $product = $variant?->product;
        $name = $product instanceof Product ? $product->name : 'item';

        // Thrown before any write, so the caller gets a usable message rather
        // than a half-applied sale.
        throw new InsufficientStock($name, $requested, $onHand);
    }

    /** Lock the level row, creating it first if this variant has never moved. */
    private function lockLevel(int $locationId, int $variantId): StockLevel
    {
        $existing = StockLevel::query()
            ->where('location_id', $locationId)
            ->where('product_variant_id', $variantId)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        // insertOrIgnore so two concurrent first-ever movements cannot both
        // insert and violate the primary key.
        DB::table('stock_levels')->insertOrIgnore([
            'location_id' => $locationId,
            'product_variant_id' => $variantId,
            'quantity' => 0,
            'reserved' => 0,
            'avg_cost_minor' => 0,
            'value_minor' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return StockLevel::query()
            ->where('location_id', $locationId)
            ->where('product_variant_id', $variantId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function writeLevel(int $locationId, int $variantId, float $quantity, int $avgCost): void
    {
        DB::table('stock_levels')
            ->where('location_id', $locationId)
            ->where('product_variant_id', $variantId)
            ->update([
                'quantity' => $quantity,
                'avg_cost_minor' => $avgCost,
                'value_minor' => (int) round($quantity * $avgCost),
                'updated_at' => now(),
            ]);
    }
}
