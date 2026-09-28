<?php

declare(strict_types=1);

use App\Domain\Central\ShopProvisioner;
use App\Domain\Verification\VerificationService;
use App\Enums\Role;
use App\Enums\VerificationStatus;
use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * The shop-facing verification form.
 *
 * Phase 16 built the domain and the operator review queue. Without this a
 * shop's legal details could only be entered on its behalf, which is an odd
 * thing to do with somebody's national ID.
 */
beforeEach(function () {
    Storage::fake('kyc');

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
function asRole(Role $role): TenantUser
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

it('is reachable by an owner', function () {
    withTenant($this->shop, function () {
        asRole(Role::Owner);

        $this->get('http://karim.lorapok.localhost/settings/verification')
            ->assertOk()
            ->assertSee('Verification');
    });
});

it('is closed to a cashier', function () {
    // A shop's legal identity is not day-to-day work, and a cashier typing
    // the owner's NID number is not a thing to make easy.
    withTenant($this->shop, function () {
        asRole(Role::Cashier);

        $this->get('http://karim.lorapok.localhost/settings/verification')->assertForbidden();
    });
});

it('is not offered in the navigation to a cashier', function () {
    withTenant($this->shop, function () {
        asRole(Role::Cashier);

        $this->get('http://karim.lorapok.localhost/')
            ->assertOk()
            ->assertDontSee('href="http://karim.lorapok.localhost/settings/verification"', escape: false);
    });
});

it('saves a half-finished form so it can be resumed', function () {
    // A trade licence number is not something anyone enjoys typing twice.
    withTenant($this->shop, function () {
        asRole(Role::Owner);

        Livewire::test('tenant.settings.verification')
            ->set('legal_name', 'Karim Mobile Enterprise')
            ->set('trade_licence_no', 'TRAD/DHK/2026/11923')
            ->set('owner_name', 'Karim Uddin')
            ->set('owner_identifier', '1990-1234567-89012')
            ->call('save')
            ->assertHasNoErrors();

        $saved = app(VerificationService::class)->forShop($this->shop);

        expect($saved->legal_name)->toBe('Karim Mobile Enterprise')
            // Still a draft — saving is not submitting.
            ->and($saved->status)->toBe(VerificationStatus::Unverified);
    });
});

it('refuses a document that is not a document', function () {
    withTenant($this->shop, function () {
        asRole(Role::Owner);

        // An executable named .jpg is the obvious attempt; the mime check is
        // what actually refuses it.
        Livewire::test('tenant.settings.verification')
            ->set('licence', UploadedFile::fake()->create('licence.exe', 100))
            ->call('upload', 'licence')
            ->assertHasErrors('licence');
    });
});

it('refuses a document too large to be a photograph', function () {
    withTenant($this->shop, function () {
        asRole(Role::Owner);

        Livewire::test('tenant.settings.verification')
            ->set('licence', UploadedFile::fake()->image('licence.jpg')->size(12000))
            ->call('upload', 'licence')
            ->assertHasErrors('licence');
    });
});

it('accepts a photograph and confirms it without offering it back', function () {
    withTenant($this->shop, function () {
        asRole(Role::Owner);

        Livewire::test('tenant.settings.verification')
            ->set('licence', UploadedFile::fake()->image('licence.jpg'))
            ->call('upload', 'licence')
            ->assertHasNoErrors()
            // Says we hold it. A shop re-downloading its own NID scan from us
            // is a copy of it in one more place.
            ->assertSee('Received');

        expect(app(VerificationService::class)->forShop($this->shop)->licence_document_path)
            ->not->toBeNull();
    });
});

it('will not send an incomplete submission', function () {
    withTenant($this->shop, function () {
        asRole(Role::Owner);

        Livewire::test('tenant.settings.verification')
            ->set('legal_name', 'Karim Mobile Enterprise')
            ->call('save')
            ->call('submit')
            ->assertSee('still missing');
    });
});

it('sends a complete submission and locks it', function () {
    withTenant($this->shop, function () {
        asRole(Role::Owner);

        $component = Livewire::test('tenant.settings.verification')
            ->set('legal_name', 'Karim Mobile Enterprise')
            ->set('trade_licence_no', 'TRAD/DHK/2026/11923')
            ->set('owner_name', 'Karim Uddin')
            ->set('owner_identifier', '1990-1234567-89012')
            ->call('save')
            ->set('licence', UploadedFile::fake()->image('licence.jpg'))
            ->call('upload', 'licence')
            ->set('identifier_front', UploadedFile::fake()->image('nid.jpg'))
            ->call('upload', 'identifier_front')
            ->call('submit');

        // Locked while it is being read, so a document cannot change halfway
        // through a review.
        $component->assertSee('cannot be changed while we are reading it');

        expect(app(VerificationService::class)->forShop($this->shop)->status)
            ->toBe(VerificationStatus::Submitted);
    });
});

it('leads with the reason when a submission was rejected', function () {
    // Created before entering tenant context: App\Models\User is central,
    // and inside a tenant the default connection points at the shop's own
    // database, where `users` is the shop's staff table.
    $operator = User::create([
        'name' => 'Operator', 'email' => 'ops@lorapok.test',
        'password' => 'password12', 'is_super_admin' => true, 'is_active' => true,
    ]);

    withTenant($this->shop, function () use ($operator) {
        asRole(Role::Owner);

        $service = app(VerificationService::class);
        $service->saveDraft($this->shop, [
            'legal_name' => 'Karim Mobile Enterprise',
            'trade_licence_no' => 'TRAD/DHK/2026/11923',
            'owner_name' => 'Karim Uddin',
            'owner_identifier' => '1990-1234567-89012',
        ]);
        $service->attachDocument($this->shop, 'licence', UploadedFile::fake()->image('a.jpg'));
        $service->attachDocument($this->shop, 'identifier_front', UploadedFile::fake()->image('b.jpg'));
        $service->submit($this->shop);

        $service->reject($service->forShop($this->shop), 'The trade licence photo is unreadable.', $operator);

        // Someone who has been rejected is here to find out why, not to
        // scroll past it.
        Livewire::test('tenant.settings.verification')
            ->assertSee('This needs fixing')
            ->assertSee('The trade licence photo is unreadable.');
    });
});
