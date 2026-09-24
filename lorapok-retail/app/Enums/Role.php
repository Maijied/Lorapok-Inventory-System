<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles inside a single shop.
 *
 * The system this replaces had one `is_admin` boolean, so a cashier and the
 * shop owner were the same thing: anyone who could ring up a sale could also
 * delete products and edit prices.
 */
enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Cashier = 'cashier';
    case StockKeeper = 'stock_keeper';
    case Accountant = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Cashier => 'Cashier',
            self::StockKeeper => 'Stock Keeper',
            self::Accountant => 'Accountant',
        };
    }

    /**
     * Permissions granted to this role.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return match ($this) {
            // The owner gets everything, including staff management and the
            // shop's own settings.
            self::Owner => Permission::all(),

            // A manager runs the shop day to day but cannot change who works
            // there or alter the shop's legal identity on invoices.
            self::Manager => array_values(array_diff(
                Permission::all(),
                [Permission::MANAGE_USERS, Permission::MANAGE_SETTINGS]
            )),

            // A cashier sells and takes payments. Deliberately cannot change a
            // product's price, void a completed sale, or see profit margin —
            // those are the levers used to hide till shortfalls.
            self::Cashier => [
                Permission::VIEW_DASHBOARD,
                Permission::VIEW_PRODUCTS,
                Permission::VIEW_CUSTOMERS,
                Permission::MANAGE_CUSTOMERS,
                Permission::CREATE_SALES,
                Permission::VIEW_SALES,
                Permission::TAKE_PAYMENTS,
            ],

            // Receives stock and counts it; no access to money.
            self::StockKeeper => [
                Permission::VIEW_DASHBOARD,
                Permission::VIEW_PRODUCTS,
                Permission::MANAGE_PRODUCTS,
                Permission::VIEW_STOCK,
                Permission::ADJUST_STOCK,
                Permission::VIEW_PURCHASES,
                Permission::MANAGE_PURCHASES,
            ],

            // Reads everything financial, changes nothing operational.
            self::Accountant => [
                Permission::VIEW_DASHBOARD,
                Permission::VIEW_PRODUCTS,
                Permission::VIEW_STOCK,
                Permission::VIEW_SALES,
                Permission::VIEW_PURCHASES,
                Permission::VIEW_CUSTOMERS,
                Permission::VIEW_REPORTS,
                Permission::VIEW_MARGIN,
            ],
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
