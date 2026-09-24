<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cross-shop analytics rollup.
     *
     * Tenant data lives in separate databases, so the super-admin dashboard
     * cannot JOIN across shops and must not fan out a query per tenant on page
     * load (that degrades linearly with tenant count). Instead a scheduled job
     * aggregates inside each tenant database and upserts one row per shop per
     * day here. Dashboards read only this table, so their cost is constant.
     */
    public function up(): void
    {
        Schema::create('tenant_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->date('date');
            // Held per row and never summed across currencies without an
            // explicit FX table — silent cross-currency addition is the classic
            // multi-tenant analytics bug.
            $table->char('currency', 3);

            $table->unsignedInteger('sales_count')->default(0);
            $table->bigInteger('sales_gross_minor')->default(0);
            $table->bigInteger('sales_discount_minor')->default(0);
            $table->bigInteger('sales_tax_minor')->default(0);
            $table->bigInteger('sales_net_minor')->default(0);
            $table->bigInteger('cogs_minor')->default(0);
            $table->bigInteger('gross_margin_minor')->default(0);

            $table->unsignedInteger('returns_count')->default(0);
            $table->bigInteger('returns_minor')->default(0);

            $table->bigInteger('payments_received_minor')->default(0);
            $table->bigInteger('outstanding_due_minor')->default(0);

            $table->unsignedInteger('purchases_count')->default(0);
            $table->bigInteger('purchases_minor')->default(0);

            $table->unsignedInteger('active_users')->default(0);
            $table->unsignedInteger('products_active')->default(0);
            $table->decimal('stock_units', 16, 4)->default(0);
            $table->bigInteger('stock_value_minor')->default(0);

            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            // Makes the rollup idempotent: re-running any day is safe.
            $table->unique(['tenant_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_daily_metrics');
    }
};
