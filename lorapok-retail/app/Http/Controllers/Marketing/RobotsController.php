<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use Illuminate\Http\Response;

/**
 * robots.txt, served rather than static.
 *
 * The shipped file was the Laravel stub — `Disallow:` with nothing after it,
 * which permits everything including the operator panel and every shop's
 * sign-in page.
 *
 * It has to be dynamic because the answer differs by host: the marketing site
 * wants crawling, a shop's subdomain does not. A static file cannot tell them
 * apart.
 */
final class RobotsController
{
    public function __invoke(): Response
    {
        $isCentral = in_array(request()->getHost(), config('tenancy.central_domains'), true);

        $body = $isCentral
            ? implode("\n", [
                'User-agent: *',
                'Allow: /',
                '',
                '# The operator panel and anything behind a session.',
                'Disallow: /admin/',
                'Disallow: /thanks/',
                '',
                'Sitemap: '.rtrim((string) config('app.url'), '/').'/sitemap.xml',
                '',
            ])
            // A shop is somebody's till. Nothing on it belongs in an index,
            // and a crawler hitting a login page repeatedly trips the rate
            // limiter for the staff who need it.
            : implode("\n", [
                'User-agent: *',
                'Disallow: /',
                '',
            ]);

        return response($body, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
