<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sign an operator out.
 *
 * A route rather than a Livewire action, because sign-out belongs in the nav
 * on every page. Until now the central panel had no way out at all short of
 * clearing cookies.
 */
final class LogoutController
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        // Both are required: invalidating alone leaves the old CSRF token
        // valid for the new session.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('central.login');
    }
}
