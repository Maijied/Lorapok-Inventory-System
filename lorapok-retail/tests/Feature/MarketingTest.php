<?php

declare(strict_types=1);

use App\Models\ContactMessage;
use App\Models\Plan;
use App\Models\ShopApplication;
use App\Models\Tenant;

/**
 * The public site.
 *
 * Until now there was none: `/` on the central domain redirected into the
 * operator panel, which is behind auth, so an anonymous visitor hit a login
 * wall and had no way to find out what the product is.
 */
const CENTRAL = 'http://lorapok.localhost';

function makePlan(string $slug, int $priceMinor, int $sortOrder = 0): Plan
{
    $plan = Plan::create([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'description' => "The {$slug} plan.",
        'price_minor' => $priceMinor,
        'sort_order' => $sortOrder,
    ]);

    $plan->limits()->create(['key' => 'products', 'value' => 500]);
    $plan->limits()->create(['key' => 'staff_accounts', 'value' => null]);

    return $plan;
}

it('serves every public page without a session', function (string $path) {
    $this->get(CENTRAL.$path)->assertOk();
})->with(['/', '/features', '/pricing', '/contact', '/start', '/legal/privacy', '/legal/terms']);

it('renders pricing from the plans table, not from markup', function () {
    makePlan('counter', 150000, 1);
    makePlan('chain', 900000, 2);

    // Changing a row must change the page, with no deploy — that is the whole
    // reason the pricing page reads the database.
    $this->get(CENTRAL.'/pricing')
        ->assertOk()
        ->assertSee('Counter')
        ->assertSee('Chain')
        ->assertSee('1,500.00')
        ->assertSee('9,000.00')
        // null means unlimited, and must read as a word rather than a blank.
        ->assertSee('Unlimited');
});

it('says so honestly when there are no plans', function () {
    // An empty table must produce an empty state, not stale hardcoded prices
    // that quietly stopped matching what anyone actually pays.
    $this->get(CENTRAL.'/pricing')
        ->assertOk()
        ->assertSee('Pricing is being finalised')
        ->assertDontSee('/ monthly');
});

it('hides inactive plans from the public page', function () {
    makePlan('counter', 150000, 1);
    makePlan('retired', 50000, 2)->update(['is_active' => false]);

    $this->get(CENTRAL.'/pricing')
        ->assertOk()
        ->assertSee('Counter')
        ->assertDontSee('Retired');
});

it('accepts a contact message', function () {
    $this->post(CENTRAL.'/contact', [
        'name' => 'Karim Uddin',
        'email' => 'karim@example.com',
        'phone' => '+8801700000000',
        'message' => 'Do you support IMEI tracking for second-hand handsets?',
        // Rendered three seconds ago, so the too-fast check passes.
        'started_at' => time() - 10,
    ])->assertRedirect(route('marketing.thanks', ['kind' => 'contact']));

    $message = ContactMessage::sole();

    expect($message->name)->toBe('Karim Uddin')
        ->and($message->email)->toBe('karim@example.com')
        // Unhandled by default, so the operator queue is "everything not yet
        // answered" rather than a flag someone has to remember to set.
        ->and($message->handled_at)->toBeNull();
});

it('rejects a submission that filled the honeypot', function () {
    // A person never sees that field. Anything in it came from something
    // filling every input on the page.
    $this->post(CENTRAL.'/contact', [
        'name' => 'Bot',
        'email' => 'bot@example.com',
        'message' => 'Buy cheap watches at example dot com right now.',
        'website' => 'http://spam.example.com',
        'started_at' => time() - 10,
    ])->assertSessionHasErrors('website');

    expect(ContactMessage::count())->toBe(0);
});

it('rejects a form completed faster than it can be read', function () {
    $this->post(CENTRAL.'/contact', [
        'name' => 'Bot',
        'email' => 'bot@example.com',
        'message' => 'Submitted the instant the page loaded.',
        'started_at' => time(),
    ])->assertSessionHasErrors('message');

    expect(ContactMessage::count())->toBe(0);
});

it('accepts a shop application', function () {
    $this->post(CENTRAL.'/start', [
        'shop_name' => 'Karim Mobile',
        'slug' => 'karim-mobile',
        'owner_name' => 'Karim Uddin',
        'owner_email' => 'karim@example.com',
        'city' => 'Dhaka',
        'started_at' => time() - 10,
    ])->assertRedirect(route('marketing.thanks', ['kind' => 'apply']));

    $application = ShopApplication::sole();

    expect($application->slug)->toBe('karim-mobile')
        ->and($application->isPending())->toBeTrue()
        // Not a shop until an operator converts it.
        ->and($application->isConverted())->toBeFalse();
});

it('refuses an application for a reserved address', function () {
    // Checked against the same list the provisioner enforces, so an applicant
    // is told immediately rather than after an operator tries to create it.
    $this->post(CENTRAL.'/start', [
        'shop_name' => 'Admin Shop',
        'slug' => Tenant::RESERVED_SLUGS[0],
        'owner_name' => 'Someone',
        'owner_email' => 'someone@example.com',
        'started_at' => time() - 10,
    ])->assertSessionHasErrors('slug');

    expect(ShopApplication::count())->toBe(0);
});

it('refuses an address that is not a valid hostname label', function () {
    // A leading or trailing hyphen is not valid in a DNS label, so the shop
    // would exist at an address nobody can reach.
    foreach (['-karim', 'karim-', 'Karim Mobile', 'ka'] as $slug) {
        $this->post(CENTRAL.'/start', [
            'shop_name' => 'Shop',
            'slug' => $slug,
            'owner_name' => 'Someone',
            'owner_email' => 'someone@example.com',
            'started_at' => time() - 10,
        ])->assertSessionHasErrors('slug');
    }

    expect(ShopApplication::count())->toBe(0);
});

it('refuses an address a shop already has', function () {
    Tenant::create(['name' => 'Karim Mobile', 'slug' => 'karim']);

    $this->post(CENTRAL.'/start', [
        'shop_name' => 'Another Karim',
        'slug' => 'karim',
        'owner_name' => 'Someone',
        'owner_email' => 'someone@example.com',
        'started_at' => time() - 10,
    ])->assertSessionHasErrors('slug');
});

it('refuses an address another application is already waiting on', function () {
    // Two people applying for the same address is a race an operator should
    // never have to resolve by hand.
    ShopApplication::create([
        'shop_name' => 'First', 'slug' => 'karim',
        'owner_name' => 'First Owner', 'owner_email' => 'first@example.com',
    ]);

    $this->post(CENTRAL.'/start', [
        'shop_name' => 'Second',
        'slug' => 'karim',
        'owner_name' => 'Second Owner',
        'owner_email' => 'second@example.com',
        'started_at' => time() - 10,
    ])->assertSessionHasErrors('slug');

    expect(ShopApplication::count())->toBe(1);
});
