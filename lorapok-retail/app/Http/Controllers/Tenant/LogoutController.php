<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sign a shop user out.
 *
 * Scoped to the `tenant` guard, so signing out of one shop never touches an
 * operator session on the central domain.
 */
final class LogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('tenant')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.login');
    }
}
