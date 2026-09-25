<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stop a suspended shop being used.
 *
 * Suspension is a billing state, not a deletion: the shop's data is intact
 * and its database untouched, so restoring is instant. Staff simply cannot
 * get in while it lasts.
 */
class BlockSuspendedShops
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant instanceof Tenant && $tenant->isSuspended()) {
            abort(403, 'This shop is currently suspended. Please contact Lorapok Labs.');
        }

        return $next($request);
    }
}
