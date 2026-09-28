<?php

declare(strict_types=1);

use App\Domain\Central\ShopProvisioner;
use App\Domain\Verification\VerificationService;
use App\Enums\VerificationStatus;
use App\Models\ShopVerification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Shop verification.
 *
 * Everything here is real personal data about a real person — a national ID,
 * a photograph, a tax number — so these assert the handling rules rather than
 * the happy path. A KYC feature that works but leaks is not a working KYC
 * feature.
 */
beforeEach(function () {
    Storage::fake('kyc');

    $this->service = app(VerificationService::class);

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

/** A verification filled in far enough to submit. */
function completeVerification(): ShopVerification
{
    $service = test()->service;
    $shop = test()->shop;

    $service->saveDraft($shop, [
        'legal_name' => 'Karim Mobile Enterprise',
        'trade_licence_no' => 'TRAD/DHK/2026/11923',
        'bin_tin' => '4417-8892-0031',
        'owner_name' => 'Karim Uddin',
        'owner_identifier' => '1990-1234567-89012',
        'address' => '14 Elephant Road',
        'city' => 'Dhaka',
    ]);

    $service->attachDocument($shop, 'licence', UploadedFile::fake()->image('licence.jpg'));
    $service->attachDocument($shop, 'identifier_front', UploadedFile::fake()->image('nid-front.jpg'));

    return $service->forShop($shop);
}

it('stores the national ID as ciphertext, not as text', function () {
    // The single most important assertion in this file. Anyone with read
    // access to the database — a backup, a replica, a support query — must
    // not be able to read somebody's national ID out of it.
    completeVerification();

    $raw = DB::table('shop_verifications')->where('tenant_id', $this->shop->id)->first();

    expect($raw->owner_identifier)->not->toContain('1990-1234567-89012')
        ->and($raw->bin_tin)->not->toContain('4417-8892-0031')
        // ...and still readable through the model, or it would be useless.
        ->and($this->service->forShop($this->shop)->owner_identifier)->toBe('1990-1234567-89012');
});

it('keeps identifiers out of anything serialised', function () {
    // A model gets dumped into a log line, an exception report or an API
    // response without anyone deciding to dump it. Those are exactly the
    // places PII leaks.
    $verification = completeVerification();

    $serialised = json_encode($verification->toArray());

    expect($serialised)->not->toContain('1990-1234567-89012')
        ->and($serialised)->not->toContain('4417-8892-0031')
        // Document paths too: a path says which shop and which document.
        ->and($serialised)->not->toContain('shops/');
});

it('discards the original filename', function () {
    // People name these after themselves. A stored path is somewhere
    // plaintext PII ends up without anyone putting it there.
    $this->service->attachDocument(
        $this->shop, 'licence',
        UploadedFile::fake()->image('karim-uddin-nid-1990-1234567.jpg'),
    );

    $path = $this->service->forShop($this->shop)->licence_document_path;

    expect($path)->not->toContain('karim')
        ->and($path)->not->toContain('1990')
        ->and($path)->toStartWith("shops/{$this->shop->id}/");
});

it('refuses to submit until the required parts are there', function () {
    $this->service->saveDraft($this->shop, ['legal_name' => 'Karim Mobile Enterprise']);

    expect(fn () => $this->service->submit($this->shop))->toThrow(DomainException::class);
});

it('accepts a complete submission', function () {
    completeVerification();

    $verification = $this->service->submit($this->shop);

    expect($verification->status)->toBe(VerificationStatus::Submitted)
        ->and($verification->submitted_at)->not->toBeNull();
});

it('freezes a submission while it is being reviewed', function () {
    // A document that changes mid-review is one a reviewer approves without
    // having seen it.
    completeVerification();
    $this->service->submit($this->shop);

    expect(fn () => $this->service->saveDraft($this->shop, ['legal_name' => 'Something Else']))
        ->toThrow(DomainException::class);
});

it('demands a reason when rejecting', function () {
    // "Rejected" with no explanation is what makes people reapply with the
    // same mistake.
    $verification = completeVerification();
    $this->service->submit($this->shop);

    expect(fn () => $this->service->reject($verification, 'no', $this->operator))
        ->toThrow(DomainException::class);
});

it('lets a rejected shop fix and resubmit', function () {
    $verification = completeVerification();
    $this->service->submit($this->shop);
    $this->service->reject($verification, 'The trade licence image is unreadable.', $this->operator);

    expect($verification->fresh()->status->isEditable())->toBeTrue();

    $this->service->saveDraft($this->shop, ['legal_name' => 'Karim Mobile Enterprise Ltd']);
    $resubmitted = $this->service->submit($this->shop);

    // The old refusal is cleared, so the shop is not left reading why it was
    // rejected while it waits again.
    expect($resubmitted->status)->toBe(VerificationStatus::Submitted)
        ->and($resubmitted->rejection_reason)->toBeNull();
});

it('records every look at a document, not just every change', function () {
    // Who looked at somebody's national ID is the question that matters
    // afterwards, and auditing writes alone cannot answer it.
    $verification = completeVerification();

    $this->service->documentUrl($verification, 'licence', $this->operator);

    $entry = DB::table('central_audit_logs')->where('action', 'kyc.document_viewed')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->actor_id)->toBe($this->operator->id)
        ->and($entry->tenant_id)->toBe($this->shop->id);
});

it('will not serve a document without a signature', function () {
    completeVerification();
    $verification = $this->service->forShop($this->shop);

    // An unsigned URL is refused even for a signed-in operator: the two
    // protections are independent on purpose.
    $this->actingAs($this->operator, 'web')
        ->get("http://lorapok.localhost/admin/kyc/{$verification->id}/licence")
        ->assertForbidden();
});

it('serves a document to an operator holding a valid signature', function () {
    completeVerification();
    $verification = $this->service->forShop($this->shop);

    $url = $this->service->documentUrl($verification, 'licence', $this->operator);

    $response = $this->actingAs($this->operator, 'web')->get($url)->assertOk();

    // Asserted on the directives that matter rather than the exact header
    // string, which Symfony normalises and reorders.
    $cacheControl = $response->headers->get('Cache-Control');

    expect($cacheControl)->toContain('no-store')      // never written to disk
        ->and($cacheControl)->toContain('private')     // never held by a proxy
        ->and($response->headers->get('Content-Disposition'))
        // inline, not attachment: a reviewer comparing a licence against a
        // form should not accumulate a folder of other people's ID documents.
        ->toContain('inline');
});

it('purges the documents of a rejected shop but keeps the decision', function () {
    // Holding somebody's national ID indefinitely after declining them is not
    // a default worth having. The record of the decision survives.
    $verification = completeVerification();
    $this->service->submit($this->shop);
    $this->service->reject($verification, 'The trade licence image is unreadable.', $this->operator);

    $deleted = $this->service->purgeDocuments($verification->fresh(), $this->operator);

    expect($deleted)->toBe(2)
        ->and($verification->fresh()->licence_document_path)->toBeNull()
        ->and($verification->fresh()->rejection_reason)->toBe('The trade licence image is unreadable.')
        ->and($verification->fresh()->status)->toBe(VerificationStatus::Rejected);
});

it('replaces a document rather than orphaning the old one', function () {
    $this->service->attachDocument($this->shop, 'licence', UploadedFile::fake()->image('first.jpg'));
    $first = $this->service->forShop($this->shop)->licence_document_path;

    $this->service->attachDocument($this->shop, 'licence', UploadedFile::fake()->image('second.jpg'));
    $second = $this->service->forShop($this->shop)->licence_document_path;

    expect($second)->not->toBe($first)
        ->and(Storage::disk('kyc')->exists($first))->toBeFalse()
        ->and(Storage::disk('kyc')->exists($second))->toBeTrue();
});
