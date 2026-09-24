<?php

declare(strict_types=1);

namespace App\Domain\Purchasing;

use App\Domain\Stock\MovementIntent;
use App\Domain\Stock\StockService;
use App\Enums\MovementType;
use App\Enums\ProductType;
use App\Enums\PurchaseStatus;
use App\Enums\StockItemStatus;
use App\Models\Tenant\GoodsReceipt;
use App\Models\Tenant\Payment;
use App\Models\Tenant\PurchaseItem;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\StockItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Buying stock.
 *
 * The central rule: a purchase order changes nothing about stock. Only a
 * goods receipt writes movements, and it does so through StockService like
 * everything else — so there is still exactly one path by which stock changes.
 *
 * That split is what makes partial deliveries ordinary rather than a special
 * case: order ten, receive four today and six next week, and the ledger
 * records two receipts with their real costs.
 */
class PurchasingService
{
    public function __construct(private StockService $stock) {}

    /** Move a draft order to ordered, after which it can receive stock. */
    public function place(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== PurchaseStatus::Draft) {
            throw new InvalidReceipt('Only a draft order can be placed.');
        }

        if ($order->items()->count() === 0) {
            throw new InvalidReceipt('Cannot place an order with no lines.');
        }

        $order->forceFill(['status' => PurchaseStatus::Ordered])->save();
        $order->recalculateTotals();

        return $order->refresh();
    }

    /**
     * Book a delivery in.
     *
     * @param  array<int, ReceiptLine>  $lines
     *
     * @throws InvalidReceipt
     */
    public function receive(
        PurchaseOrder $order,
        array $lines,
        ?int $actorId = null,
        ?string $note = null,
    ): GoodsReceipt {
        if ($lines === []) {
            throw new InvalidReceipt('A receipt must contain at least one line.');
        }

        if (! $order->status->canReceive()) {
            throw new InvalidReceipt(
                "Cannot receive against an order that is {$order->status->value}.",
            );
        }

        return DB::transaction(function () use ($order, $lines, $actorId, $note) {
            $receipt = GoodsReceipt::create([
                'number' => $this->nextNumber('GRN', GoodsReceipt::class),
                'purchase_order_id' => $order->id,
                'location_id' => $order->location_id,
                'received_at' => now(),
                'received_by' => $actorId,
                'note' => $note,
            ]);

            foreach ($lines as $line) {
                $this->receiveLine($order, $receipt, $line, $actorId);
            }

            $order->refresh()->refreshReceivedStatus();
            $order->recalculateTotals();

            return $receipt->refresh();
        });
    }

    private function receiveLine(
        PurchaseOrder $order,
        GoodsReceipt $receipt,
        ReceiptLine $line,
        ?int $actorId,
    ): void {
        /** @var PurchaseItem|null $item */
        $item = PurchaseItem::query()
            ->where('purchase_order_id', $order->id)
            ->where('id', $line->purchaseItemId)
            ->lockForUpdate()
            ->first();

        if (! $item) {
            throw new InvalidReceipt("Line {$line->purchaseItemId} does not belong to this order.");
        }

        // Over-receiving is refused rather than silently absorbed: taking in
        // more than was ordered is usually a miscount, and quietly accepting
        // it would corrupt both stock and what is owed.
        $outstanding = $item->outstandingQty();

        if ($line->qty > $outstanding + 0.0001) {
            throw new InvalidReceipt(sprintf(
                'Cannot receive %s of line %d: only %s outstanding.',
                rtrim(rtrim(number_format($line->qty, 4, '.', ''), '0'), '.'),
                $item->id,
                rtrim(rtrim(number_format($outstanding, 4, '.', ''), '0'), '.'),
            ));
        }

        $unitCost = $line->unitCostMinor ?? $item->unit_cost_minor;

        $variant = $item->variant;
        $product = $variant?->product;
        $isSerialised = $product?->type === ProductType::Serialized;

        if ($isSerialised) {
            $this->assertSerialsMatchQuantity($line, $item->id);
        }

        $receipt->items()->create([
            'purchase_item_id' => $item->id,
            'qty' => $line->qty,
            'unit_cost_minor' => $unitCost,
        ]);

        $item->forceFill([
            'qty_received' => (float) $item->qty_received + $line->qty,
        ])->save();

        if ($isSerialised) {
            // One tracked unit per serial, each carrying its own purchase
            // cost, and one stock movement each so the ledger and the unit
            // count can never disagree.
            foreach ($line->serials as $serial) {
                $stockItem = StockItem::create([
                    'product_variant_id' => $item->product_variant_id,
                    'location_id' => $order->location_id,
                    'imei' => $serial,
                    'status' => StockItemStatus::InStock,
                    'purchase_cost_minor' => $unitCost,
                ]);

                $this->stock->apply(MovementIntent::inbound(
                    $order->location_id,
                    $item->product_variant_id,
                    1,
                    MovementType::PurchaseReceipt,
                    $unitCost,
                    stockItem: $stockItem,
                    reference: $receipt,
                    actorId: $actorId,
                ));
            }

            return;
        }

        $this->stock->apply(MovementIntent::inbound(
            $order->location_id,
            $item->product_variant_id,
            $line->qty,
            MovementType::PurchaseReceipt,
            $unitCost,
            reference: $receipt,
            actorId: $actorId,
        ));
    }

    /**
     * A serialised product needs exactly one identifier per unit — that is
     * the whole point of tracking it individually.
     */
    private function assertSerialsMatchQuantity(ReceiptLine $line, int $itemId): void
    {
        $expected = (int) $line->qty;

        if ((float) $expected !== $line->qty) {
            throw new InvalidReceipt("Line {$itemId} is serialised and cannot be received in fractions.");
        }

        if (count($line->serials) !== $expected) {
            throw new InvalidReceipt(sprintf(
                'Line %d needs %d IMEI/serial number(s), %d given.',
                $itemId,
                $expected,
                count($line->serials),
            ));
        }

        $duplicates = array_diff_assoc($line->serials, array_unique($line->serials));

        if ($duplicates !== []) {
            throw new InvalidReceipt('The same IMEI cannot be entered twice: '.implode(', ', $duplicates));
        }

        $existing = StockItem::query()->whereIn('imei', $line->serials)->pluck('imei')->all();

        if ($existing !== []) {
            throw new InvalidReceipt('Already in stock: '.implode(', ', $existing));
        }
    }

    /** Record money paid to a vendor, in full or in part. */
    public function recordPayment(
        PurchaseOrder $order,
        int $amountMinor,
        ?int $paymentMethodId = null,
        ?int $actorId = null,
        ?string $reference = null,
    ): Payment {
        if ($amountMinor <= 0) {
            throw new InvalidReceipt('A payment must be greater than zero.');
        }

        return DB::transaction(function () use ($order, $amountMinor, $paymentMethodId, $actorId, $reference) {
            /** @var Payment $payment */
            $payment = $order->payments()->create([
                'payment_method_id' => $paymentMethodId,
                'amount_minor' => $amountMinor,
                'currency' => $order->currency,
                'paid_at' => now(),
                'reference' => $reference,
                'created_by' => $actorId,
            ]);

            // paid/due are always recomputed from the payments that exist.
            $order->recalculateTotals();

            return $payment;
        });
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        if (! $order->status->canCancel()) {
            throw new InvalidReceipt(
                "An order that is {$order->status->value} cannot be cancelled; return the goods instead.",
            );
        }

        $order->forceFill(['status' => PurchaseStatus::Cancelled])->save();

        return $order->refresh();
    }

    /**
     * Sequential document number, scoped to the shop's own database.
     *
     * @param  class-string<Model>  $model
     */
    public function nextNumber(string $prefix, string $model): string
    {
        $count = $model::query()->withoutGlobalScopes()->count();

        return sprintf('%s-%s-%04d', $prefix, now()->format('Ymd'), $count + 1);
    }
}
