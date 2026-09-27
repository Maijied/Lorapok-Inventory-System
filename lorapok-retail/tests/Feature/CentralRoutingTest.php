<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Routing on the central domain.
 */
it('serves the public site at the central root', function () {
    // For ten phases `/` redirected into the operator panel, which is behind
    // auth — so an anonymous visitor to the root domain hit a login wall and
    // had no way to find out what this product even is.
    $this->get('http://lorapok.localhost/')
        ->assertOk()
        ->assertSee('Lorapok Retail')
        ->assertSee('Start your shop');
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
