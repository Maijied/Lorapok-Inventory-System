<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Central\AuditLog;
use App\Domain\Impersonation\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes read-only impersonation actually read-only.
 *
 * Without this, "read-only" is a label on a token that nothing enforces — an
 * operator who entered a shop to look at a stock discrepancy is one misclick
 * from voiding a sale in a business that is not theirs.
 *
 * Enforced by HTTP method rather than by route list, deliberately: a route
 * added later is covered automatically, whereas a list is correct until
 * someone forgets to update it. Livewire is the exception that matters —
 * every one of its actions is a POST, including ones that only read — so it is
 * checked by intent rather than method.
 */
final class BlockImpersonatedWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        $impersonation = app(ImpersonationService::class);

        if (! $impersonation->active() || $impersonation->canWrite()) {
            return $next($request);
        }

        if ($this->isWrite($request)) {
            AuditLog::record(
                'impersonation.write_blocked',
                null,
                null,
                after: [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'context' => $impersonation->context(),
                ],
            );

            abort(403, 'This is a read-only support session. Ask the shop to make this change, '
                .'or start a new session with write access and a reason.');
        }

        return $next($request);
    }

    private function isWrite(Request $request): bool
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            // Livewire sends everything as POST, including a component simply
            // re-rendering after a keystroke. Blocking all of them would make
            // a read-only session unable to open a page, so the payload is
            // inspected instead: a `callMethod` is an action, an update to a
            // bound property is not.
            if ($this->isLivewire($request)) {
                return $this->livewireCallsAMethod($request);
            }

            // Signing out is a write that must always be allowed, or an
            // operator cannot leave the shop they entered.
            return ! $request->routeIs('tenant.logout');
        }

        return false;
    }

    private function isLivewire(Request $request): bool
    {
        // Livewire registers two update routes — its own hashed one
        // (livewire-<hash>/update) and the tenant-aware replacement at
        // /livewire/update. Matching on the route name covers both; a
        // substring check for 'livewire/update' silently misses the hashed
        // one, which is the route a browser actually posts to.
        return str_ends_with((string) $request->route()?->getName(), 'livewire.update')
            || str_contains($request->path(), 'livewire/update');
    }

    private function livewireCallsAMethod(Request $request): bool
    {
        foreach ((array) $request->input('components', []) as $component) {
            foreach ((array) ($component['calls'] ?? []) as $call) {
                // `$refresh` and property syncs are how a page renders at all.
                if (($call['method'] ?? '') !== '$refresh') {
                    return true;
                }
            }
        }

        return false;
    }
}
