<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Selling.
     *
     * Several deliberate corrections to the schema this replaces:
     *
     *   - sale_items.unit_price_minor is a UNIT price. The old column was
     *     called selling_price but actually held the line total, and views
     *     divided by quantity to get back to a unit price — which produced a
     *     division by zero whenever quantity was blank.
     *   - a sale records WHO made it. The old sells table had no user_id, so
     *     no sale was attributable to anyone.
     *   - money is split into subtotal, discount, tax, rounding and total, so
     *     an invoice can be shown to balance. The old table had only
     *     grand_total, supplied by the browser.
     *   - customers are real rows, not name/phone/address strings repeated on
     *     every sale.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('address')->nullable();
            $table->bigInteger('credit_limit_minor')->default(0);
            $table->bigInteger('opening_balance_minor')->default(0);
            // The anonymous over-the-counter customer, so a quick sale needs
            // no data entry but still has a customer row to hang returns off.
            $table->boolean('is_walkin')->default(false);
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
            $table->index('is_active');
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // Not nullable: every sale is attributable to the person who rang it up.
            $table->unsignedBigInteger('user_id');

            $table->string('status', 20)->default('completed');

            $table->bigInteger('subtotal_minor')->default(0);
            $table->string('discount_type', 10)->default('none');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            // Held explicitly so the printed invoice always adds up, rather
            // than being a paisa out from rounding the total.
            $table->bigInteger('rounding_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->bigInteger('paid_minor')->default(0);
            $table->bigInteger('due_minor')->default(0);
            // Cost of goods at the moment of sale, frozen. This is what makes
            // margin computable and immune to later cost changes.
            $table->bigInteger('cogs_minor')->default(0);
            $table->char('currency', 3)->default('BDT');

            $table->timestamp('sold_at');
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->string('void_reason')->nullable();
            $table->text('note')->nullable();

            // Lets an offline till replay a queued sale without creating it
            // twice. Present from the start so offline POS needs no schema
            // change later.
            $table->string('idempotency_key', 64)->nullable()->unique();

            $table->timestamps();

            $table->index(['status', 'sold_at']);
            $table->index('customer_id');
            $table->index('sold_at');
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();

            // What the product was called when it was sold. Renaming a product
            // later must not rewrite historical invoices.
            $table->string('description_snapshot');
            $table->decimal('qty', 16, 4);

            // A UNIT price, not a line total.
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->unsignedInteger('tax_rate_bps')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('line_total_minor')->default(0);
            // Frozen weighted-average cost of what went out the door.
            $table->bigInteger('cogs_minor')->default(0);
            $table->unsignedSmallInteger('warranty_days_snapshot')->nullable();

            $table->timestamps();

            $table->index('sale_id');
        });

        Schema::create('sale_item_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            // One handset can only be on one sale line.
            $table->unique('stock_item_id');
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('reason')->nullable();
            $table->bigInteger('refund_total_minor')->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->timestamp('returned_at');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('sale_id');
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 16, 4);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('refund_minor');
            // A faulty unit comes back but must not go straight back on sale.
            $table->boolean('restock')->default(true);
            $table->string('condition', 20)->default('resellable');
            $table->timestamps();

            $table->index('sale_return_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_item_serials');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('customers');
    }
};
