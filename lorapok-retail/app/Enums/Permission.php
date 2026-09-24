<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Permission names, scoped to a single shop.
 *
 * Kept as constants rather than an enum so they can be used directly in
 * `$user->can()` calls, middleware strings and Blade `@can` directives
 * without `->value` everywhere.
 */
final class Permission
{
    public const VIEW_DASHBOARD = 'view_dashboard';

    public const VIEW_PRODUCTS = 'view_products';

    public const MANAGE_PRODUCTS = 'manage_products';

    public const MANAGE_PRICES = 'manage_prices';

    public const VIEW_STOCK = 'view_stock';

    public const ADJUST_STOCK = 'adjust_stock';

    public const VIEW_PURCHASES = 'view_purchases';

    public const MANAGE_PURCHASES = 'manage_purchases';

    public const VIEW_VENDORS = 'view_vendors';

    public const MANAGE_VENDORS = 'manage_vendors';

    public const VIEW_CUSTOMERS = 'view_customers';

    public const MANAGE_CUSTOMERS = 'manage_customers';

    public const CREATE_SALES = 'create_sales';

    public const VIEW_SALES = 'view_sales';

    /** Reversing a completed sale restores stock and money — not a cashier action. */
    public const VOID_SALES = 'void_sales';

    public const TAKE_PAYMENTS = 'take_payments';

    public const MANAGE_RETURNS = 'manage_returns';

    public const VIEW_REPORTS = 'view_reports';

    /** Cost price and profit are commercially sensitive; separate from reports. */
    public const VIEW_MARGIN = 'view_margin';

    public const MANAGE_USERS = 'manage_users';

    public const MANAGE_SETTINGS = 'manage_settings';

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
