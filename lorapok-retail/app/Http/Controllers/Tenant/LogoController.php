<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Branding\LogoService;
use App\Models\Tenant;
use Illuminate\Http\Response;

/**
 * The shop's uploaded logo, if it has one.
 *
 * Public, like the generated icon beside it: a browser fetches the manifest
 * and its icons before anyone signs in, and an installed app draws its
 * home-screen icon with no session at all. What the private disk buys is an
 * unguessable path, not a locked door.
 */
class LogoController
{
    public function __invoke(LogoService $logos): Response
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        $png = $tenant instanceof Tenant ? $logos->bytesFor($tenant) : null;

        // 404 rather than quietly falling back to the generated icon. The
        // manifest only points here when a logo exists, so an empty answer
        // means it was removed since that manifest was cached — and a shop
        // that removed its logo should see the default come back, which is
        // what the next manifest fetch gives them.
        if ($png === null) {
            abort(404);
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            // Deliberately short. A shop that uploads the wrong logo should
            // see it fixed today, not next week.
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
