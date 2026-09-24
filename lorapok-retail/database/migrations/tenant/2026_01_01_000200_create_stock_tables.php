<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock as an immutable ledger.
     *
     * The system this replaces held stock as a single mutable `qty` column, so
     * history was unreconstructable, deleting a sale silently lost inventory,
     * and concurrent sales could both pass the same stock check. Here
     * `stock_movements` is append-only and authoritative; `stock_levels` is a
     * cache maintained in the same transaction and provably reconcilable.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->enum('type', ['shop', 'warehouse'])->default('shop');
            $table->json('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // IMEI / serial as a first-class inventory unit with a state machine,
        // rather than a free-text string typed onto a sale line.
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            $table->string('serial')->nullable()->unique();
            $table->string('imei', 20)->nullable()->unique();
            $table->string('imei2', 20)->nullable()->unique();

            $table->enum('status', [
                'in_stock', 'reserved', 'sold', 'returned',
                'defective', 'written_off', 'in_transit',
            ])->default('in_stock');

            $table->bigInteger('purchase_cost_minor')->default(0);
            $table->timestamp('warranty_starts_at')->nullable();
            $table->timestamp('warranty_ends_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['product_variant_id', 'status']);
            $table->index(['location_id', 'status']);
        });

        Schema::create('stock_item_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->nullableMorphs('reference');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['stock_item_id', 'created_at']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_item_id')->nullable()->constrained()->nullOnDelete();

            // Signed: positive is inbound, negative is outbound.
            $table->decimal('quantity', 16, 4);
            // Inbound: the purchase cost. Outbound: the weighted-average cost
            // frozen at the moment of sale, which is what makes historical
            // margin computable and immune to later cost changes.
            $table->bigInteger('unit_cost_minor')->nullable();

            $table->enum('type', [
                'opening', 'purchase_receipt', 'sale', 'sale_return',
                'purchase_return', 'adjustment_in', 'adjustment_out',
                'transfer_in', 'transfer_out', 'stock_take', 'write_off',
            ]);

            $table->nullableMorphs('reference');
            // Business time, which is not always insert time (backdated
            // receipts, end-of-day reconciliation).
            $table->timestamp('occurred_at');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'product_variant_id', 'occurred_at'], 'sm_loc_variant_time_idx');
            $table->index('type');
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 16, 4)->default(0);
            $table->decimal('reserved', 16, 4)->default(0);
            $table->bigInteger('avg_cost_minor')->default(0);
            $table->bigInteger('value_minor')->default(0);
            $table->timestamps();

            $table->primary(['location_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_item_events');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('locations');
    }
};
