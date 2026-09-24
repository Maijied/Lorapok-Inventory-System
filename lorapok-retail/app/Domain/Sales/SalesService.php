<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Stock\InsufficientStock;
use App\Domain\Stock\MovementIntent;
use App\Domain\Stock\StockService;
use App\Enums\DiscountType;
use App\Enums\MovementType;
use App\Enums\Permission;
use App\Enums\ProductType;
use App\Enums\ReturnCondition;
use App\Enums\SaleStatus;
use App\Enums\StockItemStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Payment;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\SaleReturn;
use App\Models\Tenant\Setting;
use App\Models\Tenant\StockItem;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Selling, returning and voiding.
 *
 * Two rules the old system broke, restored here:
 *
 *   1. The server owns pricing. A cart says what was sold and how many; every
 *      price, tax figure and total is computed here from the catalogue. The
 *      old system computed totals in browser JavaScript and saved whatever
 *      came back, so any price could be set by editing a form field.
 *
 *   2. A sale is voided, never deleted. A void writes compensating stock
 *      movements so inventory returns. Deleting a sale in the old system
 *      destroyed the stock it had consumed, permanently.
 */
class SalesService
{
    public function __construct(private StockService $stock) {}

    /**
     * Ring up a sale.
     *
     * @param  array<int, CartLine>  $lines
     *
     * @throws InvalidSale
     * @throws InsufficientStock
     */
    public function sell(
        array $lines,
        User $user,
        int $locationId,
        ?Customer $customer = null,
        DiscountType $discountType = DiscountType::None,
        int $discountValue = 0,
        ?string $idempotencyKey = null,
        ?string $note = null,
    ): Sale {
        if ($lines === []) {
            throw new InvalidSale('A sale must have at least one line.');
        }

        // An offline till replaying a queued sale must not create it twice.
        if ($idempotencyKey !== null) {
            $existing = Sale::where('idempotency_key', $idempotencyKey)->first();

            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use (
            $lines, $user, $locationId, $customer, $discountType, $discountValue, $idempotencyKey, $note
        ) {
            $sale = Sale::create([
                'number' => $this->nextNumber(),
                'location_id' => $locationId,
                'customer_id' => ($customer ?? Customer::walkIn())->id,
                'user_id' => $user->id,
                'status' => SaleStatus::Completed,
                'currency' => (string) Setting::get('locale.currency', 'BDT'),
                'sold_at' => now(),
                'idempotency_key' => $idempotencyKey,
                'note' => $note,
            ]);

            $subtotal = 0;
            $tax = 0;
            $cogs = 0;

            foreach ($lines as $line) {
                [$lineSubtotal, $lineTax, $lineCogs] = $this->addLine($sale, $line, $user, $locationId);
                $subtotal += $lineSubtotal;
                $tax += $lineTax;
                $cogs += $lineCogs;
            }

            $discount = $this->resolveDiscount($discountType, $discountValue, $subtotal);
            $rawTotal = $subtotal - $discount + $tax;
            // Rounded to the nearest whole unit of currency, with the
            // adjustment stored so the printed invoice always balances.
            $total = $this->roundTotal($rawTotal);

            $sale->forceFill([
                'subtotal_minor' => $subtotal,
                'discount_type' => $discountType,
                'discount_minor' => $discount,
                'tax_minor' => $tax,
                'rounding_minor' => $total - $rawTotal,
                'total_minor' => $total,
                'due_minor' => $total,
                'cogs_minor' => $cogs,
            ])->save();

            return $sale->refresh();
        });
    }

    /**
     * @return array{int, int, int} subtotal, tax and cost for the line
     */
    private function addLine(Sale $sale, CartLine $line, User $user, int $locationId): array
    {
        /** @var ProductVariant|null $variant */
        $variant = ProductVariant::query()->with(['product.taxRate'])->find($line->variantId);

        if (! $variant) {
            throw new InvalidSale("Unknown product variant {$line->variantId}.");
        }

        $product = $variant->product;

        if (! $product?->is_active) {
            throw new InvalidSale('This product is not available to sell.');
        }

        // The price comes from the catalogue. An override is honoured only
        // for someone allowed to manage prices; a cashier's override is
        // ignored rather than rejected, so a tampered payload simply sells at
        // the correct price.
        $unitPrice = $variant->price_minor;

        if ($line->unitPriceMinorOverride !== null && $user->can(Permission::MANAGE_PRICES)) {
            $unitPrice = $line->unitPriceMinorOverride;
        }

        $lineSubtotal = (int) round($unitPrice * $line->qty);
        $discount = min($line->discountMinor, $lineSubtotal);
        $taxable = $lineSubtotal - $discount;

        $taxRate = $product->taxRate;
        $taxBps = ($taxRate && $taxRate->is_active) ? $taxRate->rate_bps : 0;
        // Tax is computed per line and summed. Applying a rate to the order
        // total instead lets rounding drift away from the sum of the lines.
        $lineTax = (int) round($taxable * $taxBps / 10_000);

        $isSerialised = $product->type === ProductType::Serialized;

        if ($isSerialised) {
            $this->assertSerialsSelected($line, $product->name);
        }

        /** @var SaleItem $item */
        $item = $sale->items()->create([
            'product_variant_id' => $variant->id,
            // Snapshot: renaming the product later must not rewrite history.
            'description_snapshot' => $product->name,
            'qty' => $line->qty,
            'unit_price_minor' => $unitPrice,
            'discount_minor' => $discount,
            'tax_rate_bps' => $taxBps,
            'tax_minor' => $lineTax,
            'line_total_minor' => $taxable + $lineTax,
            'warranty_days_snapshot' => $product->warranty_days,
        ]);

        $cogs = $isSerialised
            ? $this->consumeSerials($item, $line, $sale, $locationId, $user)
            : $this->consumeStock($item, $variant, $line, $sale, $locationId, $user);

        $item->forceFill(['cogs_minor' => $cogs])->save();

        return [$lineSubtotal, $lineTax, $cogs];
    }

    /** Take plain stock off the shelf and capture what it cost. */
    private function consumeStock(
        SaleItem $item,
        ProductVariant $variant,
        CartLine $line,
        Sale $sale,
        int $locationId,
        User $user,
    ): int {
        if (! $variant->product?->track_stock) {
            // A service has nothing to take off a shelf.
            return 0;
        }

        $movement = $this->stock->apply(MovementIntent::outbound(
            $locationId,
            $variant->id,
            $line->qty,
            MovementType::Sale,
            reference: $sale,
            actorId: $user->id,
        ));

        return (int) round(($movement->unit_cost_minor ?? 0) * $line->qty);
    }

    /** Sell specific handsets, one movement and one status change each. */
    private function consumeSerials(
        SaleItem $item,
        CartLine $line,
        Sale $sale,
        int $locationId,
        User $user,
    ): int {
        $cogs = 0;

        foreach ($line->stockItemIds as $stockItemId) {
            /** @var StockItem|null $unit */
            $unit = StockItem::query()->lockForUpdate()->find($stockItemId);

            if (! $unit || $unit->status !== StockItemStatus::InStock) {
                throw new InvalidSale('That unit is no longer in stock.');
            }

            if ($unit->product_variant_id !== $item->product_variant_id) {
                throw new InvalidSale('That IMEI belongs to a different product.');
            }

            $movement = $this->stock->apply(MovementIntent::outbound(
                $locationId,
                $unit->product_variant_id,
                1,
                MovementType::Sale,
                stockItem: $unit,
                reference: $sale,
                actorId: $user->id,
            ));

            $unit->transitionTo(StockItemStatus::Sold, $sale, $user->id);

            $warrantyDays = $item->warranty_days_snapshot;

            if ($warrantyDays) {
                $unit->forceFill([
                    'warranty_starts_at' => now(),
                    'warranty_ends_at' => now()->addDays($warrantyDays),
                ])->save();
            }

            $item->serials()->create(['stock_item_id' => $unit->id]);

            $cogs += $movement->unit_cost_minor ?? 0;
        }

        return $cogs;
    }

    private function assertSerialsSelected(CartLine $line, string $productName): void
    {
        $expected = (int) $line->qty;

        if ((float) $expected !== $line->qty) {
            throw new InvalidSale("{$productName} is sold by the unit and cannot be split.");
        }

        if (count($line->stockItemIds) !== $expected) {
            throw new InvalidSale(sprintf(
                '%s needs %d IMEI/serial selection(s), %d given.',
                $productName,
                $expected,
                count($line->stockItemIds),
            ));
        }

        if (count(array_unique($line->stockItemIds)) !== count($line->stockItemIds)) {
            throw new InvalidSale('The same unit cannot be sold twice on one line.');
        }
    }

    private function resolveDiscount(DiscountType $type, int $value, int $subtotal): int
    {
        $discount = match ($type) {
            DiscountType::None => 0,
            DiscountType::Fixed => $value,
            // Basis points, consistent with tax rates.
            DiscountType::Percent => (int) round($subtotal * $value / 10_000),
        };

        if ($discount < 0) {
            throw new InvalidSale('A discount cannot be negative.');
        }

        // A discount larger than the sale would make the total negative.
        return min($discount, $subtotal);
    }

    /**
     * Round the payable total to the nearest whole currency unit.
     *
     * Bangladeshi retail does not settle in paisa; the adjustment is stored
     * so the invoice still balances.
     */
    private function roundTotal(int $rawTotal): int
    {
        if (! Setting::get('invoice.round_to_whole', true)) {
            return $rawTotal;
        }

        return (int) (round($rawTotal / 100) * 100);
    }

    /** Take payment, in full or in part, possibly across several methods. */
    public function recordPayment(
        Sale $sale,
        int $amountMinor,
        ?int $paymentMethodId = null,
        ?int $actorId = null,
        ?string $reference = null,
    ): Payment {
        if ($amountMinor <= 0) {
            throw new InvalidSale('A payment must be greater than zero.');
        }

        if (! $sale->status->canTakePayment()) {
            throw new InvalidSale("Cannot take payment against a {$sale->status->value} sale.");
        }

        return DB::transaction(function () use ($sale, $amountMinor, $paymentMethodId, $actorId, $reference) {
            /** @var Payment $payment */
            $payment = $sale->payments()->create([
                'payment_method_id' => $paymentMethodId,
                'amount_minor' => $amountMinor,
                'currency' => $sale->currency,
                'paid_at' => now(),
                'reference' => $reference,
                'created_by' => $actorId,
            ]);

            $sale->recalculatePayments();

            return $payment;
        });
    }

    /**
     * Reverse a sale in full.
     *
     * Writes compensating stock movements rather than removing anything, so
     * the sale remains in the record and the stock genuinely comes back.
     */
    public function void(Sale $sale, User $user, string $reason): Sale
    {
        if (! $sale->status->canVoid()) {
            throw new InvalidSale("A {$sale->status->value} sale cannot be voided.");
        }

        if ($sale->returns()->exists()) {
            throw new InvalidSale('This sale has returns against it; void is no longer possible.');
        }

        return DB::transaction(function () use ($sale, $user, $reason) {
            $sale->loadMissing('items.serials');

            foreach ($sale->items as $item) {
                $unitCost = (float) $item->qty > 0
                    ? (int) round($item->cogs_minor / (float) $item->qty)
                    : 0;

                if ($item->serials()->exists()) {
                    foreach ($item->serials as $serial) {
                        /** @var StockItem|null $unit */
                        $unit = StockItem::find($serial->stock_item_id);

                        if (! $unit) {
                            continue;
                        }

                        $this->stock->apply(MovementIntent::inbound(
                            $sale->location_id,
                            $item->product_variant_id,
                            1,
                            MovementType::SaleReturn,
                            $unit->purchase_cost_minor,
                            stockItem: $unit,
                            reference: $sale,
                            actorId: $user->id,
                            note: "Void: {$reason}",
                        ));

                        $unit->transitionTo(StockItemStatus::Returned, $sale, $user->id, $reason);
                        $unit->transitionTo(StockItemStatus::InStock, $sale, $user->id);
                    }

                    continue;
                }

                if (! $item->variant?->product?->track_stock) {
                    continue;
                }

                $this->stock->apply(MovementIntent::inbound(
                    $sale->location_id,
                    $item->product_variant_id,
                    (float) $item->qty,
                    MovementType::SaleReturn,
                    $unitCost,
                    reference: $sale,
                    actorId: $user->id,
                    note: "Void: {$reason}",
                ));
            }

            $sale->forceFill([
                'status' => SaleStatus::Void,
                'voided_at' => now(),
                'voided_by' => $user->id,
                'void_reason' => $reason,
                'due_minor' => 0,
            ])->save();

            return $sale->refresh();
        });
    }

    /**
     * Return part or all of a sale.
     *
     * @param  array<int, ReturnLine>  $lines
     */
    public function returnItems(
        Sale $sale,
        array $lines,
        User $user,
        ?string $reason = null,
    ): SaleReturn {
        if ($lines === []) {
            throw new InvalidSale('A return must have at least one line.');
        }

        if (! $sale->status->canReturn()) {
            throw new InvalidSale("Cannot return against a {$sale->status->value} sale.");
        }

        return DB::transaction(function () use ($sale, $lines, $user, $reason) {
            $return = SaleReturn::create([
                'number' => $this->nextNumber('RET', SaleReturn::class),
                'sale_id' => $sale->id,
                'location_id' => $sale->location_id,
                'user_id' => $user->id,
                'reason' => $reason,
                'currency' => $sale->currency,
                'returned_at' => now(),
            ]);

            $refundTotal = 0;

            foreach ($lines as $line) {
                $refundTotal += $this->returnLine($sale, $return, $line, $user);
            }

            $return->forceFill(['refund_total_minor' => $refundTotal])->save();

            return $return->refresh();
        });
    }

    private function returnLine(Sale $sale, SaleReturn $return, ReturnLine $line, User $user): int
    {
        /** @var SaleItem|null $item */
        $item = SaleItem::query()
            ->where('sale_id', $sale->id)
            ->where('id', $line->saleItemId)
            ->lockForUpdate()
            ->first();

        if (! $item) {
            throw new InvalidSale("Line {$line->saleItemId} does not belong to this sale.");
        }

        $returnable = $item->returnableQty();

        if ($line->qty > $returnable + 0.0001) {
            throw new InvalidSale(sprintf(
                'Cannot return %s: only %s of that line remains returnable.',
                rtrim(rtrim(number_format($line->qty, 4, '.', ''), '0'), '.'),
                rtrim(rtrim(number_format($returnable, 4, '.', ''), '0'), '.'),
            ));
        }

        // Refund at what the customer actually paid for the line, net of any
        // discount, not at today's list price.
        $netUnit = (float) $item->qty > 0
            ? (int) round(($item->line_total_minor) / (float) $item->qty)
            : 0;
        $refund = (int) round($netUnit * $line->qty);

        $return->items()->create([
            'sale_item_id' => $item->id,
            'qty' => $line->qty,
            'unit_price_minor' => $item->unit_price_minor,
            'refund_minor' => $refund,
            'restock' => $line->condition === ReturnCondition::Resellable,
            'condition' => $line->condition,
        ]);

        $unitCost = (float) $item->qty > 0
            ? (int) round($item->cogs_minor / (float) $item->qty)
            : 0;

        if ($line->stockItemIds !== []) {
            foreach ($line->stockItemIds as $stockItemId) {
                /** @var StockItem|null $unit */
                $unit = StockItem::find($stockItemId);

                if (! $unit) {
                    throw new InvalidSale('Unknown unit returned.');
                }

                $this->stock->apply(MovementIntent::inbound(
                    $sale->location_id,
                    $item->product_variant_id,
                    1,
                    MovementType::SaleReturn,
                    $unit->purchase_cost_minor,
                    stockItem: $unit,
                    reference: $return,
                    actorId: $user->id,
                ));

                $unit->transitionTo(StockItemStatus::Returned, $return, $user->id, $line->condition->value);

                // A faulty handset comes back into the building but must not
                // be sold again as new.
                $unit->transitionTo(
                    $line->condition === ReturnCondition::Resellable
                        ? StockItemStatus::InStock
                        : StockItemStatus::Defective,
                    $return,
                    $user->id,
                );
            }

            return $refund;
        }

        if ($line->condition === ReturnCondition::Resellable && $item->variant?->product?->track_stock) {
            $this->stock->apply(MovementIntent::inbound(
                $sale->location_id,
                $item->product_variant_id,
                $line->qty,
                MovementType::SaleReturn,
                $unitCost,
                reference: $return,
                actorId: $user->id,
            ));
        } elseif ($item->variant?->product?->track_stock) {
            // Defective goods are written off rather than returned to stock.
            $this->stock->apply(MovementIntent::inbound(
                $sale->location_id,
                $item->product_variant_id,
                $line->qty,
                MovementType::SaleReturn,
                $unitCost,
                reference: $return,
                actorId: $user->id,
                note: 'Returned defective',
            ));

            $this->stock->apply(MovementIntent::outbound(
                $sale->location_id,
                $item->product_variant_id,
                $line->qty,
                MovementType::WriteOff,
                reference: $return,
                actorId: $user->id,
                note: 'Defective on return',
            ));
        }

        return $refund;
    }

    /**
     * @param  class-string<Model>  $model
     */
    public function nextNumber(string $prefix = 'INV', string $model = Sale::class): string
    {
        $configured = $prefix === 'INV'
            ? (string) Setting::get('invoice.prefix', 'INV')
            : $prefix;

        $count = $model::query()->count();

        return sprintf('%s-%s-%04d', $configured, now()->format('Ymd'), $count + 1);
    }
}
