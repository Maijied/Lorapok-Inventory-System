<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why stock moved.
 *
 * Every row in `stock_movements` carries one of these. Because the ledger is
 * append-only, the reason a quantity changed is always recoverable — which it
 * was not in the old system, where stock was a single mutable number.
 */
enum MovementType: string
{
    case Opening = 'opening';
    case PurchaseReceipt = 'purchase_receipt';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case PurchaseReturn = 'purchase_return';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case StockTake = 'stock_take';
    case WriteOff = 'write_off';

    /** Inbound types add stock and therefore carry a purchase cost. */
    public function isInbound(): bool
    {
        return in_array($this, [
            self::Opening,
            self::PurchaseReceipt,
            self::SaleReturn,
            self::AdjustmentIn,
            self::TransferIn,
        ], true);
    }

    /**
     * Inbound movements change the weighted average cost. Outbound ones
     * consume it and must not, or selling stock would silently alter what the
     * remaining stock is deemed to have cost.
     */
    public function affectsAverageCost(): bool
    {
        return $this->isInbound() && $this !== self::SaleReturn;
    }

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening balance',
            self::PurchaseReceipt => 'Purchase received',
            self::Sale => 'Sale',
            self::SaleReturn => 'Sale return',
            self::PurchaseReturn => 'Returned to vendor',
            self::AdjustmentIn => 'Adjustment (in)',
            self::AdjustmentOut => 'Adjustment (out)',
            self::TransferIn => 'Transfer in',
            self::TransferOut => 'Transfer out',
            self::StockTake => 'Stock take',
            self::WriteOff => 'Write off',
        };
    }
}
