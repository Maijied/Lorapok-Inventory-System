<?php

declare(strict_types=1);

use App\Domain\Releases\ReleaseCatalog;
use App\Models\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Yaml\Yaml;

/**
 * Downloads.
 *
 * The property that matters is that this page cannot fail. It is the page
 * someone lands on to get the application, so a 500 because GitHub is having
 * a bad morning is the worst possible failure.
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

it('falls back to the committed manifest when the release API is unreachable', function () {
    Http::fake(['api.github.com/*' => fn () => throw new ConnectionException('network is down')]);

    // The floor doing its job. This is the whole reason CI commits the file:
    // an outage at GitHub must not empty the download page.
    $this->get('http://lorapok.localhost/download')
        ->assertOk()
        ->assertSee(json_decode((string) file_get_contents(resource_path('releases.json')), true)['tag']);
});

it('says so honestly when nothing has been released at all', function () {
    // Pointed at a file that is not there, because the repo's own copy stops
    // being empty the moment a real release lands — which is exactly how this
    // assertion went stale once v0.1.0 shipped.
    config()->set('releases.manifest', resource_path('does-not-exist.json'));

    Http::fake(['api.github.com/*' => fn () => throw new ConnectionException('network is down')]);

    $this->get('http://lorapok.localhost/download')
        ->assertOk()
        // Rather than rendering a download button that 404s.
        ->assertSee('No packaged builds yet');
});

it('serves the download page when the API is rate limiting us', function () {
    // 403 with x-ratelimit-remaining: 0 is what GitHub returns when the
    // unauthenticated hourly limit is spent. It must read as "no data right
    // now", never as an error page.
    Http::fake(['api.github.com/*' => Http::response([], 403, ['x-ratelimit-remaining' => '0'])]);

    $this->get('http://lorapok.localhost/download')->assertOk();
});

it('shows a real release when the API answers', function () {
    Http::fake(['api.github.com/*' => Http::response([
        'tag_name' => 'v1.2.0',
        'published_at' => '2026-09-28T10:00:00Z',
        'html_url' => 'https://github.com/example/repo/releases/tag/v1.2.0',
        'assets' => [
            ['name' => 'lorapok-retail-v1.2.0.apk', 'browser_download_url' => 'https://example.test/a.apk', 'size' => 8388608],
            ['name' => 'lorapok-self-host-v1.2.0.tar.gz', 'browser_download_url' => 'https://example.test/s.tgz', 'size' => 4096],
        ],
    ])]);

    $this->get('http://lorapok.localhost/download')
        ->assertOk()
        ->assertSee('v1.2.0')
        ->assertSee('Android')
        ->assertSee('Self-hosting')
        // Size in MB, because it is read before clicking on a metered
        // connection.
        ->assertSee('8 MB');
});

it('survives a release manifest that is malformed', function () {
    // A broken committed file must not take the page down with it — that
    // would turn a fallback into a second way to fail.
    Http::fake(['api.github.com/*' => Http::response([], 500)]);

    $path = resource_path('releases.json');
    $existing = is_file($path) ? file_get_contents($path) : null;

    try {
        file_put_contents($path, '{ not json');

        $this->get('http://lorapok.localhost/download')->assertOk();
    } finally {
        $existing === null ? @unlink($path) : file_put_contents($path, $existing);
    }
});

it('groups assets by what they actually are', function () {
    Http::fake(['api.github.com/*' => Http::response([
        'tag_name' => 'v1.0.0',
        'assets' => [
            ['name' => 'app.apk', 'browser_download_url' => 'u', 'size' => 1],
            ['name' => 'app.dmg', 'browser_download_url' => 'u', 'size' => 1],
            ['name' => 'app.AppImage', 'browser_download_url' => 'u', 'size' => 1],
            ['name' => 'lorapok-brand-v1.0.0.zip', 'browser_download_url' => 'u', 'size' => 1],
        ],
    ])]);

    expect(array_keys(app(ReleaseCatalog::class)->byPlatform()))
        ->toBe(['Android', 'macOS', 'Linux', 'Brand pack']);
});

it('serves a manifest on the apex for the Android build to read', function () {
    // Bubblewrap reads a manifest to build the app, and a TWA binds to one
    // origin. That origin has to be the apex — per-shop would mean an APK and
    // a Play listing per shop.
    $manifest = $this->get('http://lorapok.localhost/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->json();

    expect($manifest['display'])->toBe('standalone')
        ->and($manifest['icons'])->toHaveCount(2)
        ->and($manifest['icons'][0]['purpose'])->toContain('maskable');
});

it('does not publish asset links until a fingerprint is configured', function () {
    // An empty-but-valid statement file is worse than none: Chrome caches it
    // and concludes this origin is definitively not associated with any app.
    // A 404 is retried.
    config(['android.cert_fingerprints' => []]);

    $this->get('http://lorapok.localhost/.well-known/assetlinks.json')->assertNotFound();
});

it('publishes asset links once a fingerprint is configured', function () {
    config(['android.cert_fingerprints' => ['AA:BB:CC'], 'android.package' => 'tech.lorapok.retail']);

    $body = $this->get('http://lorapok.localhost/.well-known/assetlinks.json')
        ->assertOk()
        ->json();

    expect($body[0]['target']['package_name'])->toBe('tech.lorapok.retail')
        ->and($body[0]['target']['sha256_cert_fingerprints'])->toBe(['AA:BB:CC']);
});

it('serves asset links on a shop subdomain too, suspended or not', function () {
    // Deliberately not behind BlockSuspendedShops. A suspended shop that 403s
    // here leaves Chrome caching a failed verification, so its staff keep
    // seeing a URL bar after the shop is restored.
    config(['android.cert_fingerprints' => ['AA:BB:CC']]);

    $shop = Tenant::create(['name' => 'Karim Mobile', 'slug' => 'karim']);
    $shop->domains()->create(['domain' => 'karim.lorapok.localhost']);
    $shop->forceFill(['suspended_at' => now()])->save();

    $this->get('http://karim.lorapok.localhost/.well-known/assetlinks.json')->assertOk();

    // ...while the manifest IS refused for a suspended shop, which is the
    // asymmetry worth pinning.
    $this->get('http://karim.lorapok.localhost/manifest.webmanifest')->assertForbidden();
});

it('only calls artisan commands that exist from the release workflow', function () {
    $workflow = base_path('../.github/workflows/release.yml');

    preg_match_all('/php artisan ([a-z0-9:_-]+)/', (string) file_get_contents($workflow), $matches);

    $unknown = array_values(array_diff(array_unique($matches[1]), array_keys(Artisan::all())));

    expect($unknown)->toBe([], 'release.yml calls unregistered commands: '.implode(', ', $unknown));
})->skip(fn () => ! is_file(base_path('../.github/workflows/release.yml')), 'Workflows sit above the Sail mount; this runs in CI.');

it('ships the release without Android rather than failing without a keystore', function () {
    // The signing key is the one thing only the repository owner can provide.
    // A release that refuses to happen without it would block the three
    // artifacts that need no secret at all.
    $workflow = Yaml::parse((string) file_get_contents(base_path('../.github/workflows/release.yml')));

    expect($workflow['jobs']['android']['if'])->toContain('can_sign_android')
        ->and($workflow['jobs']['bundles'])->not->toHaveKey('if')
        ->and($workflow['jobs']['publish']['if'])->toContain('always()');
})->skip(fn () => ! is_file(base_path('../.github/workflows/release.yml')), 'Workflows sit above the Sail mount; this runs in CI.');
