<?php

declare(strict_types=1);

use App\Domain\Central\ShopProvisioner;
use App\Domain\Impersonation\ImpersonationService;
use App\Models\ImpersonationToken;
use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Operators entering a shop.
 *
 * This is the most dangerous capability in the system — by design, a way for
 * us to act inside somebody else's business. None of what follows stops a
 * determined operator; it makes what they did legible afterwards, which is
 * the honest goal.
 */
beforeEach(function () {
    $this->impersonation = app(ImpersonationService::class);

    $this->operator = User::create([
        'name' => 'Operator', 'email' => 'ops@lorapok.test',
        'password' => 'password12', 'is_super_admin' => true, 'is_active' => true,
    ]);

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

/** The shop's owner, from inside the shop's own database. */
function shopOwner(): TenantUser
{
    return withTenant(test()->shop, fn () => TenantUser::where('email', 'owner@karim.test')->firstOrFail());
}

it('refuses a reason that is not a reason', function () {
    // The value of this field is that someone reading the log in six months
    // understands what happened. "asked" defeats the entire mechanism.
    $owner = shopOwner();

    expect(fn () => $this->impersonation->issue($this->shop, $owner, $this->operator, 'asked'))
        ->toThrow(DomainException::class);

    expect(ImpersonationToken::count())->toBe(0);
});

it('refuses anyone who is not a super admin', function () {
    $notAnOperator = User::create([
        'name' => 'Staff', 'email' => 'staff@lorapok.test',
        'password' => 'password12', 'is_super_admin' => false, 'is_active' => true,
    ]);

    expect(fn () => $this->impersonation->issue(
        $this->shop, shopOwner(), $notAnOperator, 'Investigating a stock discrepancy'
    ))->toThrow(DomainException::class);
});

it('is read-only unless write access was explicitly asked for', function () {
    // Entering a shop to look at something must never be one typo away from
    // changing it.
    $token = $this->impersonation->issue(
        $this->shop, shopOwner(), $this->operator, 'Investigating a stock discrepancy'
    );

    expect($token->abilities)->toBe([ImpersonationToken::READ_ONLY])
        ->and($token->canWrite())->toBeFalse();
});

it('discards an ability it does not recognise', function () {
    // A typo in an ability string must not silently become write access.
    $token = $this->impersonation->issue(
        $this->shop, shopOwner(), $this->operator,
        'Checking a reported pricing bug',
        ['read-wrte', 'superuser'],
    );

    expect($token->abilities)->toBe([ImpersonationToken::READ_ONLY]);
});

it('grants write access when it is asked for, and records that it did', function () {
    $token = $this->impersonation->issue(
        $this->shop, shopOwner(), $this->operator,
        'Correcting a sale the shop cannot void themselves',
        [ImpersonationToken::READ_WRITE],
    );

    expect($token->canWrite())->toBeTrue();

    $entry = DB::table('central_audit_logs')->where('action', 'impersonation.issued')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->tenant_id)->toBe($this->shop->id)
        ->and(json_decode($entry->after, true)['reason'])
        ->toBe('Correcting a sale the shop cannot void themselves');
});

it('expires in a minute', function () {
    // A link that still works tomorrow is a standing key to somebody's shop.
    $token = $this->impersonation->issue(
        $this->shop, shopOwner(), $this->operator, 'Investigating a stock discrepancy'
    );

    expect($token->expires_at->diffInSeconds(now()))
        ->toBeLessThanOrEqual(ImpersonationToken::LIFETIME_SECONDS);
});

it('works once', function () {
    $token = $this->impersonation->issue(
        $this->shop, shopOwner(), $this->operator, 'Investigating a stock discrepancy'
    );

    withTenant($this->shop, function () use ($token) {
        $this->impersonation->consume($token);
    });

    // A reusable link is a password, and one that has been emailed around.
    expect(fn () => withTenant($this->shop, fn () => $this->impersonation->consume($token->fresh())))
        ->toThrow(DomainException::class);
});

it('refuses a token that has expired', function () {
    $token = $this->impersonation->issue(
        $this->shop, shopOwner(), $this->operator, 'Investigating a stock discrepancy'
    );

    $token->forceFill(['expires_at' => now()->subMinute()])->save();

    expect(fn () => withTenant($this->shop, fn () => $this->impersonation->consume($token)))
        ->toThrow(DomainException::class);

    // A rejected attempt is itself worth recording — it is the signal that a
    // link leaked.
    expect(DB::table('central_audit_logs')->where('action', 'impersonation.rejected')->count())->toBe(1);
});

it('records entering the shop', function () {
    $token = $this->impersonation->issue(
        $this->shop, shopOwner(), $this->operator, 'Investigating a stock discrepancy'
    );

    withTenant($this->shop, fn () => $this->impersonation->consume($token));

    expect(DB::table('central_audit_logs')->where('action', 'impersonation.started')->count())->toBe(1);
});

it('tells the shop, for the whole session', function () {
    // An operator acting inside somebody else's business without the shop
    // being able to see it is the thing this mechanism exists to prevent.
    $token = $this->impersonation->issue(
        $this->shop, shopOwner(), $this->operator, 'Investigating a stock discrepancy'
    );

    withTenant($this->shop, function () use ($token) {
        $this->impersonation->consume($token);

        $this->get('http://karim.lorapok.localhost/')
            ->assertOk()
            ->assertSee('Lorapok support is signed in to your shop')
            ->assertSee('read-only')
            ->assertSee('Investigating a stock discrepancy');
    });
});

it('blocks a write in a read-only session', function () {
    $token = $this->impersonation->issue(
        $this->shop, shopOwner(), $this->operator, 'Investigating a stock discrepancy'
    );

    withTenant($this->shop, function () use ($token) {
        $this->impersonation->consume($token);

        // Without enforcement, "read-only" is a label on a token that nothing
        // checks — and an operator is one misclick from voiding a sale in a
        // business that is not theirs.
        $this->post('http://karim.lorapok.localhost/livewire/update', [
            'components' => [['calls' => [['method' => 'save', 'params' => []]]]],
        ])->assertForbidden();
    });

    // Outside the tenant block on purpose: central_audit_logs lives in the
    // central database, and querying it while tenancy is initialised looks
    // for the table inside the shop's own schema.
    expect(DB::table('central_audit_logs')->where('action', 'impersonation.write_blocked')->count())
        ->toBe(1);
});

it('still lets a read-only session render a page', function () {
    // Livewire sends every interaction as a POST, including a component
    // simply re-rendering. Blocking by method alone would make a read-only
    // session unable to open anything.
    $token = $this->impersonation->issue(
        $this->shop, shopOwner(), $this->operator, 'Investigating a stock discrepancy'
    );

    withTenant($this->shop, function () use ($token) {
        $this->impersonation->consume($token);

        $response = $this->post('http://karim.lorapok.localhost/livewire/update', [
            'components' => [['calls' => [['method' => '$refresh', 'params' => []]]]],
        ]);

        // The payload is deliberately incomplete, so Livewire itself will
        // reject it — what matters is that it got past the guard rather than
        // being refused as a write.
        expect($response->status())->not->toBe(403);
    });
});

it('always lets an operator leave', function () {
    // Signing out is a write. Blocking it would trap an operator inside a
    // shop they entered.
    $token = $this->impersonation->issue(
        $this->shop, shopOwner(), $this->operator, 'Investigating a stock discrepancy'
    );

    withTenant($this->shop, function () use ($token) {
        $this->impersonation->consume($token);

        $this->post('http://karim.lorapok.localhost/logout')
            ->assertRedirect(route('tenant.login'));
    });
});
