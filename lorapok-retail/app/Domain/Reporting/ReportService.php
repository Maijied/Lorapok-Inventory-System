<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Enums\SaleStatus;
use App\Models\Tenant\Sale;
use App\Models\Tenant\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reporting.
 *
 * Only possible now. The old system stored no cost price at all, so profit
 * was not merely unreported but uncomputable. Every sale line now carries the
 * weighted-average cost frozen at the moment of sale, so margin is both
 * knowable and stable — restocking cheaper next week cannot retrospectively
 * change what last week's sales earned.
 *
 * Two rules throughout:
 *
 *   - voided sales are excluded everywhere, or a day's takings could be
 *     inflated by ringing up and reversing;
 *   - returns are subtracted, not ignored, so revenue is what the shop
 *     actually kept.
 */
class ReportService
{
    /**
     * Headline figures for a period.
     *
     * @return array{
     *     sales_count:int, gross_minor:int, discount_minor:int, tax_minor:int,
     *     net_minor:int, cogs_minor:int, margin_minor:int, margin_pct:float,
     *     returns_count:int, returns_minor:int, collected_minor:int, outstanding_minor:int
     * }
     */
    public function summary(DateRange $range): array
    {
        $sales = DB::table('sales')
            ->where('status', SaleStatus::Completed->value)
            ->whereBetween('sold_at', [$range->start, $range->end])
            ->selectRaw('
                COUNT(*) as sales_count,
                COALESCE(SUM(subtotal_minor), 0) as gross_minor,
                COALESCE(SUM(discount_minor), 0) as discount_minor,
                COALESCE(SUM(tax_minor), 0) as tax_minor,
                COALESCE(SUM(total_minor), 0) as total_minor,
                COALESCE(SUM(cogs_minor), 0) as cogs_minor,
                COALESCE(SUM(paid_minor), 0) as paid_minor,
                COALESCE(SUM(due_minor), 0) as due_minor
            ')
            ->first();

        $returns = DB::table('sale_returns')
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->where('sales.status', SaleStatus::Completed->value)
            ->whereBetween('sale_returns.returned_at', [$range->start, $range->end])
            ->selectRaw('COUNT(*) as returns_count, COALESCE(SUM(refund_total_minor), 0) as returns_minor')
            ->first();

        $gross = (int) ($sales->gross_minor ?? 0);
        $discount = (int) ($sales->discount_minor ?? 0);
        $cogs = (int) ($sales->cogs_minor ?? 0);
        $returnsMinor = (int) ($returns->returns_minor ?? 0);

        // What the shop kept: sold, less discounts given, less refunds paid.
        $net = $gross - $discount - $returnsMinor;
        $margin = $net - $cogs;

        return [
            'sales_count' => (int) ($sales->sales_count ?? 0),
            'gross_minor' => $gross,
            'discount_minor' => $discount,
            'tax_minor' => (int) ($sales->tax_minor ?? 0),
            'net_minor' => $net,
            'cogs_minor' => $cogs,
            'margin_minor' => $margin,
            // Guarded: a period with no sales must not divide by zero.
            'margin_pct' => $net > 0 ? round($margin / $net * 100, 2) : 0.0,
            'returns_count' => (int) ($returns->returns_count ?? 0),
            'returns_minor' => $returnsMinor,
            'collected_minor' => (int) ($sales->paid_minor ?? 0),
            'outstanding_minor' => (int) ($sales->due_minor ?? 0),
        ];
    }

    /**
     * Takings per day, for a chart or an end-of-week glance.
     *
     * @return Collection<int, \stdClass>
     */
    public function dailyTakings(DateRange $range): Collection
    {
        return DB::table('sales')
            ->where('status', SaleStatus::Completed->value)
            ->whereBetween('sold_at', [$range->start, $range->end])
            ->selectRaw('
                DATE(sold_at) as day,
                COUNT(*) as sales_count,
                COALESCE(SUM(total_minor), 0) as total_minor,
                COALESCE(SUM(subtotal_minor - discount_minor - cogs_minor), 0) as margin_minor
            ')
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }

    /**
     * Best sellers by revenue, with the margin each actually earned.
     *
     * @return Collection<int, \stdClass>
     */
    public function topProducts(DateRange $range, int $limit = 10): Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', SaleStatus::Completed->value)
            ->whereBetween('sales.sold_at', [$range->start, $range->end])
            ->selectRaw('
                sale_items.product_variant_id,
                MAX(sale_items.description_snapshot) as name,
                SUM(sale_items.qty) as qty_sold,
                SUM(sale_items.line_total_minor) as revenue_minor,
                SUM(sale_items.cogs_minor) as cogs_minor,
                SUM(sale_items.line_total_minor - sale_items.tax_minor - sale_items.cogs_minor) as margin_minor
            ')
            ->groupBy('sale_items.product_variant_id')
            ->orderByDesc('revenue_minor')
            ->limit($limit)
            ->get();
    }

    /**
     * What the shop is holding, valued at weighted-average cost.
     *
     * Cost, not retail: this is the money tied up in stock, which is the
     * figure that matters for cash flow.
     *
     * @return array{units:float, value_minor:int, line_count:int}
     */
    public function stockValuation(): array
    {
        $row = DB::table('stock_levels')
            // `lines` is a reserved word in MySQL 8 and cannot be used as an
            // alias without quoting.
            ->selectRaw('
                COUNT(*) as line_count,
                COALESCE(SUM(quantity), 0) as units,
                COALESCE(SUM(value_minor), 0) as value_minor
            ')
            ->first();

        return [
            'units' => (float) ($row->units ?? 0),
            'value_minor' => (int) ($row->value_minor ?? 0),
            'line_count' => (int) ($row->line_count ?? 0),
        ];
    }

    /**
     * Products at or below their reorder level.
     *
     * Excludes services and anything not stock-tracked, which would
     * otherwise appear permanently out of stock.
     *
     * @return Collection<int, \stdClass>
     */
    public function lowStock(int $limit = 50): Collection
    {
        return DB::table('products')
            ->join('product_variants', 'product_variants.product_id', '=', 'products.id')
            ->leftJoin('stock_levels', 'stock_levels.product_variant_id', '=', 'product_variants.id')
            ->whereNull('products.deleted_at')
            ->where('products.is_active', true)
            ->where('products.track_stock', true)
            ->where('products.reorder_level', '>', 0)
            ->whereRaw('COALESCE(stock_levels.quantity, 0) <= products.reorder_level')
            ->selectRaw('
                products.id,
                products.name,
                products.sku,
                products.reorder_level,
                COALESCE(stock_levels.quantity, 0) as on_hand
            ')
            ->orderBy('on_hand')
            ->limit($limit)
            ->get();
    }

    /**
     * Money owed to the shop by customers.
     *
     * @return Collection<int, \stdClass>
     */
    public function customerDues(int $limit = 50): Collection
    {
        return DB::table('sales')
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.status', SaleStatus::Completed->value)
            ->where('sales.due_minor', '>', 0)
            ->selectRaw('
                customers.id,
                customers.name,
                customers.phone,
                COUNT(sales.id) as open_sales,
                SUM(sales.due_minor) as due_minor
            ')
            ->groupBy('customers.id', 'customers.name', 'customers.phone')
            ->orderByDesc('due_minor')
            ->limit($limit)
            ->get();
    }

    /**
     * Money the shop owes vendors.
     *
     * @return Collection<int, \stdClass>
     */
    public function vendorDues(int $limit = 50): Collection
    {
        return DB::table('purchase_orders')
            ->join('vendors', 'vendors.id', '=', 'purchase_orders.vendor_id')
            ->whereNull('purchase_orders.deleted_at')
            ->where('purchase_orders.due_minor', '>', 0)
            ->whereIn('purchase_orders.status', ['ordered', 'partial', 'received'])
            ->selectRaw('
                vendors.id,
                vendors.name,
                COUNT(purchase_orders.id) as open_orders,
                SUM(purchase_orders.due_minor) as due_minor
            ')
            ->groupBy('vendors.id', 'vendors.name')
            ->orderByDesc('due_minor')
            ->limit($limit)
            ->get();
    }

    /**
     * Takings split by how customers paid.
     *
     * @return Collection<int, \stdClass>
     */
    public function takingsByMethod(DateRange $range): Collection
    {
        return DB::table('payments')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->where('payments.payable_type', Sale::class)
            ->whereBetween('payments.paid_at', [$range->start, $range->end])
            ->selectRaw("
                COALESCE(payment_methods.name, 'Unspecified') as method,
                COUNT(*) as payment_count,
                COALESCE(SUM(payments.amount_minor), 0) as amount_minor
            ")
            ->groupBy('method')
            ->orderByDesc('amount_minor')
            ->get();
    }

    /** The shop's own timezone, so "today" means their trading day. */
    public function timezone(): string
    {
        return (string) Setting::get('locale.timezone', 'UTC');
    }
}
