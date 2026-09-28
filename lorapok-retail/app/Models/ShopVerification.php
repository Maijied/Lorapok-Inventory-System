<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What we hold about who a shop is.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string|null $legal_name
 * @property string|null $trade_licence_no
 * @property string|null $bin_tin encrypted at rest
 * @property string|null $owner_identifier encrypted at rest
 * @property VerificationStatus $status
 * @property string|null $rejection_reason
 * @property Carbon|null $submitted_at
 */
class ShopVerification extends Model
{
    protected $guarded = [];

    /** @var array<string, string> */
    protected $attributes = ['status' => 'unverified'];

    /**
     * Never serialised, in any direction.
     *
     * An NID or a TIN in a log line, an API response or an exception report is
     * a leak — and those are exactly the places a model gets dumped without
     * anyone deciding to dump it.
     *
     * @var list<string>
     */
    protected $hidden = [
        'bin_tin',
        'owner_identifier',
        'licence_document_path',
        'identifier_front_path',
        'identifier_back_path',
        'owner_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'status' => VerificationStatus::class,
            // Laravel's encrypted cast: ciphertext in the column, plaintext
            // in PHP. Unsearchable by design — a query that could find a shop
            // by NID is a query that could enumerate them.
            'bin_tin' => 'encrypted',
            'owner_identifier' => 'encrypted',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The documents held, as disk paths.
     *
     * @return array<string, string|null>
     */
    public function documents(): array
    {
        return [
            'licence' => $this->licence_document_path,
            'identifier_front' => $this->identifier_front_path,
            'identifier_back' => $this->identifier_back_path,
            'owner_photo' => $this->owner_photo_path,
        ];
    }

    /** Whether everything needed for a review is present. */
    public function isSubmittable(): bool
    {
        return filled($this->legal_name)
            && filled($this->trade_licence_no)
            && filled($this->owner_name)
            && filled($this->owner_identifier)
            && filled($this->licence_document_path)
            && filled($this->identifier_front_path);
    }
}
