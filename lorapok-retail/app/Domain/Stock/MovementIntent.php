<?php

declare(strict_types=1);

namespace App\Domain\Stock;

use App\Enums\MovementType;
use App\Models\Tenant\Location;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\StockItem;
use Illuminate\Database\Eloquent\Model;

/**
 * A request to move stock.
 *
 * Immutable and validated at construction, so an impossible movement cannot
 * be constructed and then half-applied.
 */
final readonly class MovementIntent
{
    private function __construct(
        public int $locationId,
        public int $variantId,
        public float $quantity,
        public MovementType $type,
        public ?int $unitCostMinor = null,
        public ?int $stockItemId = null,
        public ?Model $reference = null,
        public ?int $actorId = null,
        public ?string $note = null,
        public ?\DateTimeInterface $occurredAt = null,
    ) {}

    /**
     * Stock coming in. Quantity is given as a positive number and stored
     * positive; the unit cost is required because it feeds the weighted
     * average that makes margin computable.
     */
    public static function inbound(
        Location|int $location,
        ProductVariant|int $variant,
        float $quantity,
        MovementType $type,
        int $unitCostMinor,
        ?StockItem $stockItem = null,
        ?Model $reference = null,
        ?int $actorId = null,
        ?string $note = null,
        ?\DateTimeInterface $occurredAt = null,
    ): self {
        if ($quantity <= 0) {
            throw new InvalidMovement('Inbound quantity must be greater than zero.');
        }

        if (! $type->isInbound()) {
            throw new InvalidMovement("{$type->value} is not an inbound movement type.");
        }

        if ($unitCostMinor < 0) {
            throw new InvalidMovement('Unit cost cannot be negative.');
        }

        return new self(
            locationId: $location instanceof Location ? $location->id : $location,
            variantId: $variant instanceof ProductVariant ? $variant->id : $variant,
            quantity: $quantity,
            type: $type,
            unitCostMinor: $unitCostMinor,
            stockItemId: $stockItem?->id,
            reference: $reference,
            actorId: $actorId,
            note: $note,
            occurredAt: $occurredAt,
        );
    }

    /**
     * Stock going out. Quantity is given positive for readability at the call
     * site and stored negative, so summing the ledger yields the balance.
     */
    public static function outbound(
        Location|int $location,
        ProductVariant|int $variant,
        float $quantity,
        MovementType $type,
        ?StockItem $stockItem = null,
        ?Model $reference = null,
        ?int $actorId = null,
        ?string $note = null,
        ?\DateTimeInterface $occurredAt = null,
    ): self {
        if ($quantity <= 0) {
            throw new InvalidMovement('Outbound quantity must be given as a positive number.');
        }

        if ($type->isInbound()) {
            throw new InvalidMovement("{$type->value} is not an outbound movement type.");
        }

        return new self(
            locationId: $location instanceof Location ? $location->id : $location,
            variantId: $variant instanceof ProductVariant ? $variant->id : $variant,
            quantity: -$quantity,
            type: $type,
            // Cost is not supplied by the caller: it is the current weighted
            // average, frozen onto the movement by StockService.
            unitCostMinor: null,
            stockItemId: $stockItem?->id,
            reference: $reference,
            actorId: $actorId,
            note: $note,
            occurredAt: $occurredAt,
        );
    }

    public function isInbound(): bool
    {
        return $this->quantity > 0;
    }

    public function absoluteQuantity(): float
    {
        return abs($this->quantity);
    }
}
