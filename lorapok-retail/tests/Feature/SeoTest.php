<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Seo;

/**
 * What crawlers and link previews are told.
 *
 * The shipped robots.txt was the Laravel stub — `Disallow:` with nothing after
 * it, which permits crawling everything, including the operator panel and
 * every shop's sign-in page.
 */
afterEach(function () {
    tenancy()->end();

    Tenant::query()->get()->each(function (Tenant $tenant) {
        try {
            $tenant->database()->manager()->deleteDatabase($tenant);
        } catch (Throwable) {
            // Already gone.
        }
        Tenant::withoutEvents(fn () => $tenant->delete());
    });
});

it('builds one canonical address from config, never from the request', function () {
    // A page reached at a stray host or with tracking parameters attached must
    // still declare one address, or every variant competes with the original.
    $seo = new Seo(title: 'Pricing', description: 'x', path: '/pricing');

    expect($seo->canonical())->toBe(rtrim((string) config('app.url'), '/').'/pricing')
        ->and((new Seo(title: 'Home', description: 'x', path: '/'))->canonical())
        ->toBe(rtrim((string) config('app.url'), '/').'/');
});

it('does not repeat the product name in a title that already carries it', function () {
    $named = new Seo(title: 'Lorapok Retail for phone shops', description: 'x');

    expect($named->fullTitle())->toBe('Lorapok Retail for phone shops')
        ->and((new Seo(title: 'Pricing', description: 'x'))->fullTitle())
        ->toBe('Pricing — '.config('app.name'));
});

it('emits a canonical and a social card on every public page', function (string $path) {
    $this->get('http://lorapok.localhost'.$path)
        ->assertOk()
        ->assertSee('rel="canonical"', escape: false)
        ->assertSee('property="og:image"', escape: false)
        ->assertSee('name="twitter:card"', escape: false);
})->with(['/', '/features', '/pricing', '/contact', '/start']);

it('keeps pages that should not rank out of the index', function (string $path) {
    // They exist and are linked, but a legal page or a thank-you page has
    // nothing to offer a search result — and indexing a thank-you invites
    // people to land there having submitted nothing.
    $this->get('http://lorapok.localhost'.$path)
        ->assertOk()
        ->assertSee('name="robots" content="noindex, follow"', escape: false);
})->with(['/legal/privacy', '/legal/terms', '/thanks/contact']);

it('claims an offer only when a real plan exists', function () {
    // Structured data with a price of zero, because the table happens to be
    // empty, is a lie told to a crawler.
    $this->get('http://lorapok.localhost/')
        ->assertOk()
        ->assertDontSee('"@type":"Offer"', escape: false);

    Plan::create(['name' => 'Counter', 'slug' => 'counter', 'price_minor' => 150000]);

    $this->get('http://lorapok.localhost/')
        ->assertOk()
        ->assertSee('"@type":"Offer"', escape: false)
        ->assertSee('"price":"1500.00"', escape: false);
});

it('generates the sitemap from the route table', function () {
    $body = $this->get('http://lorapok.localhost/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->getContent();

    expect($body)->toContain('<loc>'.rtrim((string) config('app.url'), '/').'/pricing</loc>')
        ->toContain('/features')
        // Excluded: noindex pages, and the sitemap itself — a sitemap listing
        // itself tells a crawler to fetch the page it is already reading.
        ->not->toContain('/legal/privacy')
        ->not->toContain('/thanks/')
        ->not->toContain('sitemap.xml</loc>');
});

it('lists no shop subdomain in the sitemap', function () {
    $tenant = Tenant::create(['name' => 'Karim Mobile', 'slug' => 'karim']);
    $tenant->domains()->create(['domain' => 'karim.lorapok.localhost']);

    // Shops are private tills, not public pages. Publishing them invites a
    // crawler into a customer's business.
    expect($this->get('http://lorapok.localhost/sitemap.xml')->getContent())
        ->not->toContain('karim');
});

it('invites crawlers on the marketing site', function () {
    $body = $this->get('http://lorapok.localhost/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->getContent();

    expect($body)->toContain('Allow: /')
        ->toContain('Disallow: /admin/')
        ->toContain('Sitemap: ');
});

it('keeps crawlers off a shop entirely', function () {
    $tenant = Tenant::create(['name' => 'Karim Mobile', 'slug' => 'karim']);
    $tenant->domains()->create(['domain' => 'karim.lorapok.localhost']);

    // The answer has to differ by host, which is why robots.txt is served
    // rather than static. A crawler hammering a shop's login page also trips
    // the rate limiter for the staff who need it.
    $body = $this->get('http://karim.lorapok.localhost/robots.txt')->assertOk()->getContent();

    expect(trim($body))->toContain('Disallow: /')
        ->not->toContain('Allow: /');
});
