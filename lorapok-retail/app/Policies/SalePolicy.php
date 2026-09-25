<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Tenant\Sale;
use App\Models\Tenant\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::VIEW_SALES);
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->can(Permission::VIEW_SALES);
    }

    /** Ringing up a sale is the cashier's core job. */
    public function create(User $user): bool
    {
        return $user->can(Permission::CREATE_SALES);
    }

    /**
     * Reversing a completed sale restores stock and money, so it is kept
     * away from the till: it is one of the levers used to hide a shortfall.
     */
    public function void(User $user, Sale $sale): bool
    {
        return $user->can(Permission::VOID_SALES);
    }

    public function refund(User $user, Sale $sale): bool
    {
        return $user->can(Permission::MANAGE_RETURNS);
    }

    public function takePayment(User $user, Sale $sale): bool
    {
        return $user->can(Permission::TAKE_PAYMENTS);
    }

    /**
     * Reports are readable by anyone with view_reports; cost and margin are
     * gated separately by view_margin, because they are commercially
     * sensitive in a way that a day's takings is not.
     */
    public function viewReports(User $user): bool
    {
        return $user->can(Permission::VIEW_REPORTS);
    }
}
