<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How far a shop has got with verification.
 *
 * Verification gates *billing*, not *selling*. A shop that has not sent its
 * papers yet still trades on a trial — holding the till hostage to paperwork
 * would punish the shop for our process.
 */
enum VerificationStatus: string
{
    case Unverified = 'unverified';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Not started',
            self::Submitted => 'Awaiting review',
            self::UnderReview => 'Being reviewed',
            self::Verified => 'Verified',
            self::Rejected => 'Needs attention',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Verified => 'positive',
            self::Submitted, self::UnderReview => 'info',
            self::Rejected => 'negative',
            self::Unverified => 'neutral',
        };
    }

    /** Whether the shop can still change what it submitted. */
    public function isEditable(): bool
    {
        // Not while someone is reading it — a document changing mid-review is
        // how a reviewer ends up approving something they did not see.
        return match ($this) {
            self::Unverified, self::Rejected => true,
            self::Submitted, self::UnderReview, self::Verified => false,
        };
    }

    public function isComplete(): bool
    {
        return $this === self::Verified;
    }
}
