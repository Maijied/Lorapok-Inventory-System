<?php

declare(strict_types=1);

use App\Domain\Branding\LogoService;
use App\Domain\Central\ShopProvisioner;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * A shop's own logo.
 *
 * `tenants.logo_path` existed from Phase 1 with nothing writing to it, so
 * every installed till showed a generated initial.
 *
 * Both bugs that shipped in Phase 16 were in components no test ever
 * rendered, so the screen here is rendered, not just the service behind it.
 */
beforeEach(function () {
    Storage::fake('logos');

    $this->shop = app(ShopProvisioner::class)->create(
        name: 'Karim Mobile', slug: 'karim',
        ownerName: 'Karim Uddin', ownerEmail: 'owner@karim.test', ownerPassword: 'password12',
    );
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

/** Sign in as a shop user holding one role. */
function asShopRole(Role $role): TenantUser
{
    $user = TenantUser::firstWhere('email', strtolower($role->value).'@karim.test')
        ?? TenantUser::create([
            'name' => $role->label(),
            'email' => strtolower($role->value).'@karim.test',
            'password' => 'password12',
            'is_active' => true,
        ])->syncRoles([$role->value]);

    return actingAsTenantUser($user);
}

/** A real PNG, because the service reads it with GD rather than trusting it. */
function fakeLogo(int $width = 400, int $height = 200): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 90));

    ob_start();
    imagepng($image);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return UploadedFile::fake()->createWithContent('logo.png', $bytes);
}

it('is reachable by an owner', function () {
    withTenant($this->shop, function () {
        asShopRole(Role::Owner);

        $this->get('http://karim.lorapok.localhost/settings/branding')
            ->assertOk()
            ->assertSee('Using the generated icon');
    });
});

it('is closed to a cashier', function () {
    // What every member of staff sees on their home screen is not a cashier's
    // call, for the same reason the shop's name is not.
    withTenant($this->shop, function () {
        asShopRole(Role::Cashier);

        $this->get('http://karim.lorapok.localhost/settings/branding')->assertForbidden();
    });
});

it('stores an uploaded logo as a square png', function () {
    withTenant($this->shop, function () {
        asShopRole(Role::Owner);

        Livewire::test('tenant.settings.branding')
            ->set('logo', fakeLogo())
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Using your logo');
    });

    $this->shop->refresh();

    expect($this->shop->logo_path)->toBeString()->not->toBe('');

    // Re-encoded rather than stored as uploaded: 400x200 comes back square.
    $size = getimagesizefromstring(Storage::disk('logos')->get($this->shop->logo_path));

    expect($size[0])->toBe(512)
        ->and($size[1])->toBe(512)
        ->and($size['mime'])->toBe('image/png');
});

it('points the manifest at the logo once there is one', function () {
    withTenant($this->shop, function () {
        app(LogoService::class)->store($this->shop, fakeLogo());
    });

    $icons = $this->get('http://karim.lorapok.localhost/manifest.webmanifest')
        ->assertOk()
        ->json('icons');

    expect($icons)->toHaveCount(1)
        ->and($icons[0]['type'])->toBe('image/png')
        ->and($icons[0]['src'])->toContain('/logo.png');
});

it('serves the logo with no session, because an installed app has none', function () {
    // Nothing uploaded: the route exists and has nothing to give.
    $this->get('http://karim.lorapok.localhost/logo.png')->assertNotFound();

    withTenant($this->shop, function () {
        app(LogoService::class)->store($this->shop, fakeLogo());
    });

    $response = $this->get('http://karim.lorapok.localhost/logo.png')->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('image/png');
});

it('refuses an SVG whatever it is named', function () {
    // This file ends up as an icon on somebody's phone, and an SVG can carry
    // script. A .png extension does not make it a PNG.
    withTenant($this->shop, function () {
        $svg = UploadedFile::fake()->createWithContent(
            'logo.png',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        expect(fn () => app(LogoService::class)->store($this->shop, $svg))
            ->toThrow(DomainException::class);
    });

    expect($this->shop->refresh()->logo_path)->toBeNull();
});

it('falls back to the generated icon once the logo is removed', function () {
    $path = withTenant($this->shop, function () {
        $path = app(LogoService::class)->store($this->shop, fakeLogo());

        app(LogoService::class)->remove($this->shop);

        return $path;
    });

    expect($this->shop->refresh()->logo_path)->toBeNull()
        // Removed from disk, not merely unlinked from the row.
        ->and(Storage::disk('logos')->exists($path))->toBeFalse();

    expect($this->get('http://karim.lorapok.localhost/manifest.webmanifest')->json('icons'))
        ->toHaveCount(2)
        ->and($this->get('http://karim.lorapok.localhost/logo.png')->status())->toBe(404);
});

it('replaces rather than accumulates on a second upload', function () {
    withTenant($this->shop, function () {
        $first = app(LogoService::class)->store($this->shop, fakeLogo(400, 200));
        $second = app(LogoService::class)->store($this->shop, fakeLogo(300, 300));

        expect($second)->not->toBe($first)
            ->and(Storage::disk('logos')->exists($first))->toBeFalse()
            ->and(Storage::disk('logos')->exists($second))->toBeTrue();
    });
});
