<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Permission for one operator to enter one shop, once.
 *
 * Every column here is a constraint, not metadata:
 *
 *   `reason`      mandatory. Support work always has one, and requiring it
 *                 written down is what makes the audit trail worth reading.
 *   `abilities`   defaults to read-only. Entering a shop to look at something
 *                 must never be one typo away from changing it.
 *   `expires_at`  short. A link that works tomorrow is a standing key.
 *   `consumed_at` single use. A reusable link is a password.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $tenant_user_id
 * @property int $super_admin_id
 * @property string $reason
 * @property array<int, string> $abilities
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
class ImpersonationToken extends Model
{
    use HasUuids;

    public const READ_ONLY = 'read-only';

    public const READ_WRITE = 'read-write';

    /** Long enough to click, short enough that a leaked link is worthless. */
    public const LIFETIME_SECONDS = 60;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'super_admin_id');
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }

    /**
     * Whether this session may change anything.
     *
     * Read-write is never the default and never implied: an operator has to
     * have asked for it, and the reason they gave is attached to the request
     * that granted it.
     */
    public function canWrite(): bool
    {
        return in_array(self::READ_WRITE, $this->abilities, true);
    }
}
