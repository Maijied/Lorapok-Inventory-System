<?php

declare(strict_types=1);

namespace App\Domain\Verification;

use App\Domain\Central\AuditLog;
use App\Enums\VerificationStatus;
use App\Models\ShopVerification;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Shop verification.
 *
 * Everything here is real personal data about a real person — a national ID,
 * a photograph, a tax number — so the rules are stricter than the rest of the
 * application and stated rather than assumed:
 *
 *   - Identifiers are encrypted at rest and never logged.
 *   - Documents live on a private disk and are only ever reachable through a
 *     short-lived signed URL.
 *   - Every *view* of a document is recorded, not just every change. Who
 *     looked at someone's ID is the question that matters afterwards.
 *   - A rejection carries a reason, because "rejected" alone makes people
 *     reapply with the same mistake.
 *   - Rejected submissions are purgeable. Holding someone's ID indefinitely
 *     after declining them is not something to do by default.
 */
final class VerificationService
{
    private const DISK = 'kyc';

    /** Long enough to open a document, short enough that a copied link dies. */
    private const LINK_LIFETIME_MINUTES = 5;

    public function forShop(Tenant $tenant): ShopVerification
    {
        return ShopVerification::firstOrCreate(['tenant_id' => $tenant->id]);
    }

    /**
     * Save what the shop has filled in so far.
     *
     * @param  array<string, mixed>  $fields
     */
    public function saveDraft(Tenant $tenant, array $fields): ShopVerification
    {
        $verification = $this->forShop($tenant);

        if (! $verification->status->isEditable()) {
            throw new DomainException(
                'This submission is being reviewed and cannot be changed. '
                .'A document that changes mid-review is one a reviewer approves without having seen.'
            );
        }

        $verification->fill(array_intersect_key($fields, array_flip([
            'legal_name', 'trade_licence_no', 'bin_tin',
            'owner_name', 'owner_identifier', 'address', 'city',
        ])))->save();

        // Deliberately not audited field-by-field: a before/after of an
        // encrypted identifier would write the plaintext into the audit table
        // and undo the encryption.
        return $verification;
    }

    /**
     * Store one document against a shop's verification.
     */
    public function attachDocument(Tenant $tenant, string $kind, UploadedFile $file): ShopVerification
    {
        $column = match ($kind) {
            'licence' => 'licence_document_path',
            'identifier_front' => 'identifier_front_path',
            'identifier_back' => 'identifier_back_path',
            'owner_photo' => 'owner_photo_path',
            default => throw new DomainException("Unknown document type: {$kind}"),
        };

        $verification = $this->forShop($tenant);

        if (! $verification->status->isEditable()) {
            throw new DomainException('This submission is being reviewed and cannot be changed.');
        }

        // Stored under the tenant id with a generated name. The original
        // filename is discarded: people name these things after themselves,
        // and a path is a place plaintext PII leaks without anyone deciding
        // to put it there.
        $path = $file->store("shops/{$tenant->id}", self::DISK);

        if ($path === false) {
            throw new DomainException('Could not store that document. Please try again.');
        }

        // Replacing a document removes the old one rather than orphaning it.
        $previous = $verification->{$column};

        $verification->forceFill([$column => $path])->save();

        if (is_string($previous) && $previous !== '' && Storage::disk(self::DISK)->exists($previous)) {
            Storage::disk(self::DISK)->delete($previous);
        }

        AuditLog::record(
            'kyc.document_attached',
            $verification,
            null,
            after: ['kind' => $kind],
            tenant: $tenant,
        );

        return $verification;
    }

    /**
     * Hand the submission to us for review.
     */
    public function submit(Tenant $tenant): ShopVerification
    {
        $verification = $this->forShop($tenant);

        if (! $verification->isSubmittable()) {
            throw new DomainException('Some required details or documents are still missing.');
        }

        $verification->forceFill([
            'status' => VerificationStatus::Submitted,
            'submitted_at' => now(),
            // A resubmission clears the previous refusal, so a shop is not
            // left reading why it was rejected while it waits again.
            'rejection_reason' => null,
        ])->save();

        AuditLog::record('kyc.submitted', $verification, null, tenant: $tenant);

        return $verification;
    }

    /**
     * A short-lived link to one document, for one operator.
     *
     * Every issue is recorded. Who looked at somebody's national ID, and when,
     * is the question that matters after the fact — and it is not answerable
     * by auditing writes alone.
     */
    public function documentUrl(ShopVerification $verification, string $kind, User $operator): string
    {
        $path = $verification->documents()[$kind] ?? null;

        if (! is_string($path) || $path === '') {
            throw new DomainException('No such document.');
        }

        AuditLog::record(
            'kyc.document_viewed',
            $verification,
            $operator,
            after: ['kind' => $kind],
            tenant: $verification->tenant,
        );

        return URL::temporarySignedRoute(
            'central.kyc.document',
            now()->addMinutes(self::LINK_LIFETIME_MINUTES),
            ['verification' => $verification->id, 'kind' => $kind],
        );
    }

    public function beginReview(ShopVerification $verification, User $operator): ShopVerification
    {
        $verification->forceFill(['status' => VerificationStatus::UnderReview])->save();

        AuditLog::record('kyc.review_started', $verification, $operator, tenant: $verification->tenant);

        return $verification;
    }

    public function approve(ShopVerification $verification, User $operator): ShopVerification
    {
        return DB::transaction(function () use ($verification, $operator): ShopVerification {
            $verification->forceFill([
                'status' => VerificationStatus::Verified,
                'reviewed_at' => now(),
                'reviewed_by' => $operator->id,
                'rejection_reason' => null,
            ])->save();

            AuditLog::record('kyc.approved', $verification, $operator, tenant: $verification->tenant);

            return $verification;
        });
    }

    public function reject(ShopVerification $verification, string $reason, User $operator): ShopVerification
    {
        $reason = trim($reason);

        // A refusal with no explanation makes a shop reapply with the same
        // mistake, which wastes their time and ours.
        if (mb_strlen($reason) < 10) {
            throw new DomainException('Say why, in enough detail that the shop can fix it.');
        }

        return DB::transaction(function () use ($verification, $reason, $operator): ShopVerification {
            $verification->forceFill([
                'status' => VerificationStatus::Rejected,
                'rejection_reason' => $reason,
                'reviewed_at' => now(),
                'reviewed_by' => $operator->id,
            ])->save();

            AuditLog::record(
                'kyc.rejected',
                $verification,
                $operator,
                after: ['reason' => $reason],
                tenant: $verification->tenant,
            );

            return $verification;
        });
    }

    /**
     * Delete the documents of a rejected submission.
     *
     * Holding somebody's national ID indefinitely after declining them is not
     * a default worth having. The record of the decision survives; the
     * photographs do not.
     */
    public function purgeDocuments(ShopVerification $verification, ?User $operator = null): int
    {
        $deleted = 0;

        foreach ($verification->documents() as $path) {
            if (is_string($path) && $path !== '' && Storage::disk(self::DISK)->exists($path)) {
                Storage::disk(self::DISK)->delete($path);
                $deleted++;
            }
        }

        $verification->forceFill([
            'licence_document_path' => null,
            'identifier_front_path' => null,
            'identifier_back_path' => null,
            'owner_photo_path' => null,
        ])->save();

        AuditLog::record(
            'kyc.documents_purged',
            $verification,
            $operator,
            after: ['files_deleted' => $deleted],
            tenant: $verification->tenant,
        );

        return $deleted;
    }
}
