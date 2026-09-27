<?php

declare(strict_types=1);

use App\Domain\Central\ShopProvisioner;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Symfony\Component\Yaml\Yaml;

/**
 * The deployment pipeline.
 *
 * These assert the things that are only discovered at 3am otherwise: a
 * workflow calling an artisan command that does not exist, or an image that
 * bakes in configuration it cannot know at build time.
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

it('queues one migration job per shop rather than blocking the deploy', function () {
    // Inline migration is fine for four shops and wrong for fifty: the deploy
    // sits blocked while the previous image still serves. Per-shop jobs also
    // isolate a failure — one bad schema lands in failed_jobs instead of
    // stalling every shop behind it.
    app(ShopProvisioner::class)->create(
        name: 'Karim Mobile', slug: 'karim',
        ownerName: 'Owner', ownerEmail: 'owner@karim.test', ownerPassword: 'password12',
    );
    app(ShopProvisioner::class)->create(
        name: 'Nasir Telecom', slug: 'nasir',
        ownerName: 'Owner', ownerEmail: 'owner@nasir.test', ownerPassword: 'password12',
    );

    Queue::fake();

    Artisan::call('tenants:migrate-queued');

    Queue::assertPushed(MigrateDatabase::class, 2);
});

it('says so plainly when there are no shops', function () {
    Queue::fake();

    Artisan::call('tenants:migrate-queued');

    expect(Artisan::output())->toContain('No shops to migrate');
    Queue::assertNothingPushed();
});

/**
 * The workflows live at the repository root, one level above the application.
 * Sail mounts only the application directory, so they are unreachable locally
 * — but CI checks out the whole repository, which is where these matter.
 */
function deployWorkflow(): string
{
    return base_path('../.github/workflows/deploy.yml');
}

it('only calls artisan commands that exist', function () {
    // A deploy workflow referencing a command that was never written fails at
    // the worst possible moment. `tenants:migrate --queue` was in this
    // workflow and that flag does not exist.
    $workflow = deployWorkflow();

    preg_match_all('/php artisan ([a-z0-9:_-]+)/', (string) file_get_contents($workflow), $matches);

    expect($matches[1])->not->toBeEmpty();

    $known = array_keys(Artisan::all());

    foreach (array_unique($matches[1]) as $command) {
        expect($known)->toContain($command, "deploy.yml calls `php artisan {$command}`, which is not registered");
    }
})->skip(fn () => ! is_file(deployWorkflow()), 'Workflows are above the Sail mount; this runs in CI.');

it('can cache its routes, which the production image requires', function () {
    // `route:cache` refuses duplicate route names. routes/web.php registers
    // the central routes once per entry in `central_domains` — three in
    // development — so naming them on every pass produced three routes called
    // `home`. Uncached the last one silently wins; cached, it fails outright.
    //
    // That meant the production image could not be built at all, and nothing
    // before the first deploy would have shown it.
    $exit = Artisan::call('route:cache');

    try {
        expect($exit)->toBe(0, Artisan::output());
    } finally {
        // Never leave a cached route table behind for the next test.
        Artisan::call('route:clear');
    }
});

it('does not bake configuration into the image', function () {
    // config:cache freezes env() at BUILD time. The image is built once and
    // deployed where the database and Redis credentials only exist at run
    // time, so a cached config would pin whatever the builder had — nothing.
    $dockerfile = (string) file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)->not->toMatch('/artisan config:cache/')
        // route: and view: caches read no environment, so they are safe to bake.
        ->and($dockerfile)->toContain('artisan route:cache')
        ->and($dockerfile)->toContain('artisan view:cache');
});

it('runs the container as a non-root user', function () {
    $dockerfile = (string) file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)->toContain('USER lorapok')
        // Binding :80 unprivileged needs the capability granted explicitly,
        // or the container starts as non-root and then cannot listen.
        ->and($dockerfile)->toContain('CAP_NET_BIND_SERVICE');
});

it('keeps the deploy dormant until credentials exist', function () {
    // The pipeline is merged before it can run. Without this gate every push
    // to main would fail on a missing token, and a permanently red CI gets
    // ignored.
    $workflow = Yaml::parse((string) file_get_contents(deployWorkflow()));

    expect($workflow['jobs']['deploy']['if'])->toContain('can_deploy')
        ->and($workflow['jobs']['smoke']['if'])->toContain('can_deploy')
        // The image still builds regardless, so the build is proven long
        // before anyone tries to deploy it.
        ->and($workflow['jobs']['build'])->not->toHaveKey('if');
})->skip(fn () => ! is_file(deployWorkflow()), 'Workflows are above the Sail mount; this runs in CI.');
