<?php

declare(strict_types=1);

use App\Domain\Central\ShopProvisioner;
use App\Models\Tenant;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->provisioner = app(ShopProvisioner::class);
});

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

function pwaShop(string $slug, string $name, ?string $accent = null): Tenant
{
    return test()->provisioner->create(
        name: $name,
        slug: $slug,
        ownerName: 'Owner',
        ownerEmail: "owner@{$slug}.test",
        ownerPassword: 'password12',
        accent: $accent,
    );
}

it('serves a manifest carrying the shop own identity', function () {
    pwaShop('karim', 'Karim Mobile');

    $manifest = $this->get('http://karim.lorapok.localhost/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->json();

    // An installed app must appear on the cashier's home screen as the shop,
    // not as "Lorapok Retail" — which a static manifest cannot do.
    expect($manifest['name'])->toBe('Karim Mobile')
        ->and($manifest['display'])->toBe('standalone')
        // The till is what this gets installed for.
        ->and($manifest['start_url'])->toBe('/pos');
});

it('gives each shop a different manifest', function () {
    pwaShop('karim', 'Karim Mobile');
    pwaShop('nasir', 'Nasir Telecom');

    $karim = $this->get('http://karim.lorapok.localhost/manifest.webmanifest')->json('name');
    $nasir = $this->get('http://nasir.lorapok.localhost/manifest.webmanifest')->json('name');

    expect($karim)->toBe('Karim Mobile')
        ->and($nasir)->toBe('Nasir Telecom');
});

it('offers maskable icons so Android does not letterbox them', function () {
    pwaShop('karim', 'Karim Mobile');

    $icons = $this->get('http://karim.lorapok.localhost/manifest.webmanifest')->json('icons');

    expect($icons)->toHaveCount(2)
        ->and($icons[0]['purpose'])->toContain('maskable')
        ->and(collect($icons)->pluck('sizes')->all())->toBe(['192x192', '512x512']);
});

it('generates an icon in the shop accent colour', function () {
    $shop = pwaShop('karim', 'Karim Mobile', Tenant::ACCENT_PALETTE[2]);

    $svg = $this->get('http://karim.lorapok.localhost/icon-192.svg')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->getContent();

    // No upload step: a new shop has a usable icon immediately.
    expect($svg)->toContain(Tenant::ACCENT_PALETTE[2])
        ->and($svg)->toContain('>K<');
});

it('does not render an unsafe accent into the icon', function () {
    $shop = pwaShop('karim', 'Karim Mobile');

    // The column is varchar(7), so a long injection payload cannot even be
    // stored — the schema is a defence in its own right. This is the worst
    // that fits, and it still must not reach the SVG.
    $shop->forceFill(['accent' => '#gg"><'])->save();

    $svg = $this->get('http://karim.lorapok.localhost/icon-192.svg')->getContent();

    expect($svg)->not->toContain('#gg"><')
        // Falls back to the Lorapok violet rather than rendering the value.
        ->and($svg)->toContain('#7c5cff');
});

it('cannot store an accent long enough to break out of the attribute', function () {
    $shop = pwaShop('karim', 'Karim Mobile');

    // Belt and braces: the column width is asserted, because widening it
    // later would quietly remove a layer of protection.
    expect(fn () => $shop->forceFill(['accent' => '" onload="alert(1)'])->save())
        ->toThrow(QueryException::class);
});

it('serves the manifest and icon without signing in', function () {
    pwaShop('karim', 'Karim Mobile');

    // A browser fetches the manifest before anyone signs in, and an installed
    // app needs its icon whether or not there is a session.
    $this->get('http://karim.lorapok.localhost/manifest.webmanifest')->assertOk();
    $this->get('http://karim.lorapok.localhost/icon-512.svg')->assertOk();
});

it('links the manifest and theme colour from every shop page', function () {
    pwaShop('karim', 'Karim Mobile');

    $this->get('http://karim.lorapok.localhost/login')
        ->assertOk()
        ->assertSee('rel="manifest"', escape: false)
        ->assertSee('name="theme-color"', escape: false);
});

it('warns a cashier that they are offline', function () {
    pwaShop('karim', 'Karim Mobile');

    // Told immediately, rather than discovering it when checkout fails.
    $this->get('http://karim.lorapok.localhost/login')
        ->assertSee('data-offline-banner', escape: false)
        ->assertSee('sales cannot be completed');
});

it('refuses a manifest for a suspended shop', function () {
    $shop = pwaShop('karim', 'Karim Mobile');
    $this->provisioner->suspend($shop);

    // Suspension applies to every tenant route, the manifest included.
    $this->get('http://karim.lorapok.localhost/manifest.webmanifest')->assertForbidden();
});

it('never caches the Livewire update endpoint in the service worker', function () {
    // Serving a stale component payload corrupts component state in ways
    // that are very hard to diagnose, so this rule is asserted rather than
    // trusted to survive future edits.
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)->toContain("url.pathname.includes('/livewire/')")
        ->and($sw)->toContain('isNeverCacheable')
        // Only GETs are ever cached.
        ->and($sw)->toContain("request.method !== 'GET'");
});

it('keeps the operator panel out of the offline cache', function () {
    $sw = file_get_contents(public_path('sw.js'));

    // The central admin has nothing to gain from stale pages.
    expect($sw)->toContain("url.pathname.startsWith('/admin/')");
});
