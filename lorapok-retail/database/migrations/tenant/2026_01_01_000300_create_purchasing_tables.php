<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Purchasing: where stock comes from.
     *
     * The old system had no concept of buying inventory at all. Stock only
     * ever went down (via a sale) or was typed in by hand on the product
     * form, which left no record of what was bought, from whom, or at what
     * price — and therefore no cost basis for margin.
     *
     * The ordering/receiving split matters: a purchase order is an intention,
     * a goods receipt is an event. Only the receipt writes stock movements,
     * which is what makes partial deliveries work without special cases.
     */
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('address')->nullable();
            // What the shop already owed this vendor when the system started.
            $table->bigInteger('opening_balance_minor')->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('phone');
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();

            $table->string('status', 20)->default('draft');
            $table->date('expected_at')->nullable();

            // Money is always integer minor units with an explicit currency.
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            // Derived from `payments`, recomputed in the same transaction.
            $table->bigInteger('paid_minor')->default(0);
            $table->bigInteger('due_minor')->default(0);
            $table->char('currency', 3)->default('BDT');

            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index('vendor_id');
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();

            $table->decimal('qty_ordered', 16, 4);
            // Tracked separately so a partially delivered line is visible
            // without inspecting every receipt.
            $table->decimal('qty_received', 16, 4)->default(0);

            $table->bigInteger('unit_cost_minor');
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('tax_rate_bps')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('line_total_minor')->default(0);
            $table->timestamps();

            $table->index('purchase_order_id');
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->timestamp('received_at');
            $table->unsignedBigInteger('received_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('purchase_order_id');
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 16, 4);
            // Captured per receipt: the same line can be delivered twice at
            // different prices, and the ledger needs the real cost each time.
            $table->bigInteger('unit_cost_minor');
            $table->timestamps();

            $table->index('goods_receipt_id');
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('cash');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Polymorphic so the same table serves purchases now and sales in
            // the next phase; a payment is a payment either way.
            $table->morphs('payable');
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('BDT');
            $table->timestamp('paid_at');
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('vendors');
    }
};
