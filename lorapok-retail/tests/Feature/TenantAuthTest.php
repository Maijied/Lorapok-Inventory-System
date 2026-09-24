<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\Tenant;
use App\Models\Tenant\Setting;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->createdTenants = collect();

    $this->tenant = Tenant::create(['name' => 'Karim Mobile', 'slug' => 'karim']);
    $this->tenant->domains()->create(['domain' => 'karim.lorapok.localhost']);
    $this->createdTenants->push($this->tenant);

    tenancy()->initialize($this->tenant);

    $this->owner = User::create([
        'name' => 'Karim Uddin',
        'email' => 'owner@karim.test',
        'password' => 'password',
        'is_active' => true,
    ])->syncRoles(['owner']);
});

afterEach(function () {
    tenancy()->end();

    $this->createdTenants->each(function (Tenant $tenant) {
        try {
            $tenant->database()->manager()->deleteDatabase($tenant);
        } catch (Throwable) {
            // Already gone.
        }
        Tenant::withoutEvents(fn () => $tenant->delete());
    });
});

it('seeds roles, permissions and settings when a shop is provisioned', function () {
    expect(Role::count())->toBe(5)
        ->and(Spatie\Permission\Models\Permission::count())->toBe(count(Permission::all()))
        ->and(Setting::get('locale.currency'))->toBe('BDT')
        // The shop name comes from the tenant, not a hard-coded template.
        ->and(Setting::get('shop.name'))->toBe('Karim Mobile');
});

it('logs a shop user in with valid credentials', function () {
    Livewire::test('tenant.auth.login')
        ->set('email', 'owner@karim.test')
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('tenant.dashboard'));

    expect(Auth::guard('tenant')->check())->toBeTrue()
        ->and(Auth::guard('tenant')->user()->email)->toBe('owner@karim.test');
});

it('records the last login time', function () {
    expect($this->owner->last_login_at)->toBeNull();

    Livewire::test('tenant.auth.login')
        ->set('email', 'owner@karim.test')
        ->set('password', 'password')
        ->call('login');

    expect($this->owner->fresh()->last_login_at)->not->toBeNull();
});

it('rejects a wrong password without revealing whether the account exists', function () {
    Livewire::test('tenant.auth.login')
        ->set('email', 'owner@karim.test')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    expect(Auth::guard('tenant')->check())->toBeFalse();
});

it('refuses a deactivated account', function () {
    $this->owner->update(['is_active' => false]);

    Livewire::test('tenant.auth.login')
        ->set('email', 'owner@karim.test')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    expect(Auth::guard('tenant')->check())->toBeFalse();
});

it('rate limits repeated failed logins', function () {
    // The system this replaces overrode the login controller and lost
    // Laravel's throttling with it, allowing unlimited password guessing.
    $component = Livewire::test('tenant.auth.login')
        ->set('email', 'owner@karim.test')
        ->set('password', 'wrong');

    foreach (range(1, 5) as $ignored) {
        $component->call('login');
    }

    $component->call('login')->assertHasErrors('email');

    // Even the correct password is refused while the lockout holds.
    $component->set('password', 'password')->call('login');
    expect(Auth::guard('tenant')->check())->toBeFalse();

    RateLimiter::clear('login|'.$this->tenant->id.'|owner@karim.test|127.0.0.1');
});

it('gives the owner every permission and the cashier a restricted set', function () {
    $cashier = User::create([
        'name' => 'Cashier', 'email' => 'cashier@karim.test',
        'password' => 'password', 'is_active' => true,
    ])->syncRoles(['cashier']);

    expect($this->owner->getAllPermissions()->count())->toBe(count(Permission::all()));

    // A cashier sells but must not be able to change prices, void a completed
    // sale, or see profit margin.
    expect($cashier->can(Permission::CREATE_SALES))->toBeTrue()
        ->and($cashier->can(Permission::TAKE_PAYMENTS))->toBeTrue()
        ->and($cashier->can(Permission::MANAGE_PRICES))->toBeFalse()
        ->and($cashier->can(Permission::VOID_SALES))->toBeFalse()
        ->and($cashier->can(Permission::VIEW_MARGIN))->toBeFalse()
        ->and($cashier->can(Permission::MANAGE_USERS))->toBeFalse();
});

it('redirects guests to the shop login page', function () {
    $this->get('http://karim.lorapok.localhost/')
        ->assertRedirect(route('tenant.login'));
});

it('lets an authenticated user reach the dashboard', function () {
    Auth::guard('tenant')->login($this->owner);

    $this->get('http://karim.lorapok.localhost/')
        ->assertOk()
        ->assertSee('Karim Mobile');
});
