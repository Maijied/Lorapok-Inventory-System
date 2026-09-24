<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Initialise tenancy only when the request is for a shop subdomain.
 *
 * Needed for routes that are shared by the central app and every tenant —
 * principally Livewire's update endpoint, which Livewire registers once,
 * globally, outside our tenant route group.
 *
 * Without this, a Livewire action fired from a shop page arrives with no
 * tenant initialised and silently queries the CENTRAL database. The symptom
 * is obscure: logging in as a shop user fails with "Unknown column
 * users.deleted_at", because the tenant User model is being run against the
 * central users table.
 *
 * Plain InitializeTenancyByDomain cannot be used here, because it throws when
 * the host is a central domain — which would break Livewire for the
 * super-admin panel.
 */
class InitializeTenancyIfTenantDomain
{
    public function __construct(private InitializeTenancyByDomain $initializeTenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isCentralDomain($request)) {
            return $next($request);
        }

        return $this->initializeTenancy->handle($request, $next);
    }

    private function isCentralDomain(Request $request): bool
    {
        return in_array($request->getHost(), config('tenancy.central_domains', []), true);
    }
}
