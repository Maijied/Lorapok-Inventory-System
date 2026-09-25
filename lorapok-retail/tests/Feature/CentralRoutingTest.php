<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Routing on the central domain.
 *
 * Replaces Laravel's stub test, which asserted that `/` returns 200. The root
 * of the central app is now the operator panel, so it redirects rather than
 * rendering a page.
 */
it('sends the central root to the operator panel', function () {
    $this->get('http://lorapok.localhost/')
        ->assertRedirect(route('central.shops'));
});

it('sends an unauthenticated operator to the central login page', function () {
    // Central and tenant apps have separate sign-in pages; a guest on the
    // central host must not be sent to a shop's login.
    $this->get('http://lorapok.localhost/admin/shops')
        ->assertRedirect(route('central.login'));
});

it('shows the central login page', function () {
    $this->get('http://lorapok.localhost/admin/login')
        ->assertOk()
        ->assertSee('Operator sign in');
});

it('lets a signed-in operator reach the shops screen', function () {
    $admin = User::create([
        'name' => 'Operator', 'email' => 'admin@lorapok.tech',
        'password' => 'password', 'is_super_admin' => true, 'is_active' => true,
    ]);

    Auth::guard('web')->login($admin);

    $this->get('http://lorapok.localhost/admin/shops')
        ->assertOk()
        ->assertSee('Shops');
});
