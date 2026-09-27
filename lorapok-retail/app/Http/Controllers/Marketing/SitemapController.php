<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/**
 * sitemap.xml, generated from the route table.
 *
 * Derived rather than hand-maintained: a hand-written sitemap is correct on
 * the day it is written and silently wrong from the first route added after
 * it. Any route named `marketing.*` that answers GET and takes no parameters
 * is a public page by definition, so that is the rule.
 *
 * Shop subdomains are deliberately absent. They are private tills, not public
 * pages, and listing them would invite crawling a customer's shop.
 */
final class SitemapController
{
    /** Pages that exist but should not rank; they carry noindex too. */
    private const EXCLUDED = [
        'marketing.thanks',
        'marketing.privacy',
        'marketing.terms',
        // A sitemap that lists itself is a page telling a crawler to fetch the
        // page it is already reading.
        'marketing.sitemap',
    ];

    public function __invoke(): Response
    {
        $base = rtrim((string) config('app.url'), '/');

        $urls = collect(Route::getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'marketing.')
                && in_array('GET', $route->methods(), true)
                // A route with a parameter has no single address to publish.
                && $route->parameterNames() === []
                && ! in_array($route->getName(), self::EXCLUDED, true))
            // The same path is registered once per central domain, so
            // deduplicate or every URL appears three times.
            ->map(fn ($route): string => '/'.ltrim($route->uri(), '/'))
            ->unique()
            ->sort()
            ->values();

        $xml = view('marketing.sitemap', [
            'base' => $base,
            'paths' => $urls,
        ])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
