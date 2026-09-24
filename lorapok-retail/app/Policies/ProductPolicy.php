<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Tenant\Product;
use App\Models\Tenant\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::VIEW_PRODUCTS);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can(Permission::VIEW_PRODUCTS);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::MANAGE_PRODUCTS);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can(Permission::MANAGE_PRODUCTS);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can(Permission::MANAGE_PRODUCTS);
    }

    /**
     * Pricing is separate from product management on purpose: a stock keeper
     * maintains the catalogue but must not be able to change what things sell
     * for, and a cashier can do neither.
     */
    public function managePrices(User $user): bool
    {
        return $user->can(Permission::MANAGE_PRICES);
    }

    /** Cost price reveals margin, which is commercially sensitive. */
    public function viewCost(User $user): bool
    {
        return $user->can(Permission::VIEW_MARGIN);
    }
}
